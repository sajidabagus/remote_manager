using System.Diagnostics;
using System.IO.Pipes;
using System.Net;
using System.Net.NetworkInformation;
using System.Net.Http.Json;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json.Nodes;
using Microsoft.Extensions.Options;
namespace RcmAgent;

public class AgentOptions { public string ServerUrl { get; set; } = ""; public int HeartbeatSeconds { get; set; } = 15; }

// Token disimpan terenkripsi DPAPI (mesin) di ProgramData, folder dibatasi untuk SYSTEM + Administrators.
public class TokenStore
{
    static readonly string Dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "RCM");
    static readonly string DataFile = Path.Combine(Dir, "agent.dat");
    public string? Load()
    {
        try { return File.Exists(DataFile) ? Encoding.UTF8.GetString(ProtectedData.Unprotect(File.ReadAllBytes(DataFile), null, DataProtectionScope.LocalMachine)) : null; }
        catch { return null; }
    }
    public void Save(string token)
    {
        Directory.CreateDirectory(Dir);
        using (var p = Process.Start(new ProcessStartInfo("icacls.exe", $"\"{Dir}\" /inheritance:r /grant *S-1-5-18:(OI)(CI)F /grant *S-1-5-32-544:(OI)(CI)F") { CreateNoWindow = true, UseShellExecute = false })) p?.WaitForExit(5000);
        File.WriteAllBytes(DataFile, ProtectedData.Protect(Encoding.UTF8.GetBytes(token), null, DataProtectionScope.LocalMachine));
    }
    public void Clear() { try { File.Delete(DataFile); } catch { } }
}

public class ApiClient
{
    readonly HttpClient _http = new() { Timeout = TimeSpan.FromSeconds(15) };
    readonly string _base;
    public ApiClient(IOptions<AgentOptions> o) => _base = o.Value.ServerUrl.TrimEnd('/');
    public async Task<(HttpStatusCode Code, JsonNode? Body)> Post(string route, object body, string? token, CancellationToken ct)
    {
        using var req = new HttpRequestMessage(HttpMethod.Post, $"{_base}/index.php?r={route}") { Content = JsonContent.Create(body) };
        if (token != null) req.Headers.Add("X-Agent-Token", token);
        using var res = await _http.SendAsync(req, ct);
        var txt = await res.Content.ReadAsStringAsync(ct);
        JsonNode? n = null; try { n = JsonNode.Parse(txt); } catch { }
        return (res.StatusCode, n);
    }
}

public static class NetInfo
{
    // NIC aktif (Ethernet/Wi-Fi) yang punya gateway IPv4; Ethernet diutamakan. Adapter virtual biasanya tanpa gateway.
    public static string? PrimaryMac()
    {
        var nic = NetworkInterface.GetAllNetworkInterfaces()
            .Where(n => n.OperationalStatus == OperationalStatus.Up
                     && n.NetworkInterfaceType is NetworkInterfaceType.Ethernet or NetworkInterfaceType.Wireless80211
                     && n.GetIPProperties().GatewayAddresses.Any(g => g.Address.AddressFamily == System.Net.Sockets.AddressFamily.InterNetwork && !g.Address.Equals(IPAddress.Any)))
            .OrderBy(n => n.NetworkInterfaceType == NetworkInterfaceType.Ethernet ? 0 : 1).FirstOrDefault();
        var raw = nic?.GetPhysicalAddress().ToString();
        return string.IsNullOrEmpty(raw) || raw.Length != 12 ? null : string.Join(":", Enumerable.Range(0, 6).Select(i => raw.Substring(i * 2, 2)));
    }
}

// Hanya tipe tugas yang dikenal; tidak ada eksekusi perintah/path bebas dari server.
public class TaskRunner(ILogger<TaskRunner> log)
{
    public async Task<(bool Ok, string Msg)> Run(string type, JsonNode? payload, CancellationToken ct)
    {
        log.LogInformation("Menjalankan tugas {type}", type);
        return type switch
        {
            "ping" => (true, $"pong dari {Environment.MachineName}"),
            "restart" => Sys("/r"),
            "shutdown" => Sys("/s"),
            "lock" or "unlock" or "message" or "open_url" or "run_app" => await ViaLauncher(type, payload, ct), // Fase 3
            _ => (false, "tipe tugas tidak dikenal")
        };
    }
    static (bool, string) Sys(string flag)
    {
        try { Process.Start(new ProcessStartInfo("shutdown.exe", $"{flag} /t 10 /c \"Perintah dari admin RCM\"") { CreateNoWindow = true, UseShellExecute = false }); return (true, "dijadwalkan 10 detik lagi"); }
        catch (Exception e) { return (false, e.Message); }
    }
    // Tugas yang tampil di layar diteruskan ke Launcher (sesi user) lewat named pipe.
    static async Task<(bool, string)> ViaLauncher(string type, JsonNode? payload, CancellationToken ct)
    {
        try
        {
            using var pipe = new NamedPipeClientStream(".", "RcmLauncher", PipeDirection.InOut, PipeOptions.Asynchronous);
            await pipe.ConnectAsync(3000, ct);
            using var w = new StreamWriter(pipe) { AutoFlush = true }; using var r = new StreamReader(pipe);
            await w.WriteLineAsync(new JsonObject { ["type"] = type, ["payload"] = payload?.DeepClone() }.ToJsonString());
            var reply = JsonNode.Parse(await r.ReadLineAsync(ct) ?? "{}");
            return (reply?["ok"]?.GetValue<bool>() ?? false, reply?["message"]?.GetValue<string>() ?? "");
        }
        catch (TimeoutException) { return (false, "Launcher belum berjalan di sesi user"); }
        catch (Exception e) { return (false, "Launcher error: " + e.Message); }
    }
}
