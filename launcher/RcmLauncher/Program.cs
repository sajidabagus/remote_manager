using System.Diagnostics;
using System.IO.Pipes;
using System.Media;
using System.Security.AccessControl;
using System.Security.Principal;
using System.Text.Json.Nodes;
namespace RcmLauncher;

static class Program
{
    [STAThread]
    static void Main()
    {
        using var mutex = new Mutex(true, @"Local\RcmLauncher", out bool first);
        if (!first) return; // satu instance per sesi user
        ApplicationConfiguration.Initialize();
        Application.Run(new Host());
    }
}

// Berjalan tanpa jendela di sesi user. Menerima perintah dari RcmAgent (SYSTEM) lewat named pipe.
class Host : ApplicationContext
{
    readonly Control _ui = new(); readonly LockManager _lock = new();
    public Host() { _ = _ui.Handle; Task.Run(Serve); }

    async Task Serve()
    {
        var sec = new PipeSecurity(); // hanya SYSTEM yang boleh terhubung; user biasa/siswa tidak bisa
        sec.AddAccessRule(new PipeAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null), PipeAccessRights.ReadWrite, AccessControlType.Allow));
        sec.AddAccessRule(new PipeAccessRule(WindowsIdentity.GetCurrent().User!, PipeAccessRights.CreateNewInstance, AccessControlType.Allow));
        using var pipe = NamedPipeServerStreamAcl.Create("RcmLauncher", PipeDirection.InOut, 1, PipeTransmissionMode.Byte, PipeOptions.Asynchronous, 0, 0, sec);
        while (true)
        {
            try
            {
                await pipe.WaitForConnectionAsync();
                using var cts = new CancellationTokenSource(5000);
                using var r = new StreamReader(pipe, leaveOpen: true); using var w = new StreamWriter(pipe, leaveOpen: true) { AutoFlush = true };
                var req = JsonNode.Parse(await r.ReadLineAsync(cts.Token) ?? "{}");
                (bool ok, string msg) res = (false, "perintah kosong");
                var type = req?["type"]?.GetValue<string>(); var payload = req?["payload"];
                if (type != null) _ui.Invoke(() => { try { res = Handle(type, payload); } catch (Exception e) { res = (false, e.Message); } });
                await w.WriteLineAsync(new JsonObject { ["ok"] = res.ok, ["message"] = res.msg }.ToJsonString());
            }
            catch { }
            finally { try { if (pipe.IsConnected) pipe.Disconnect(); } catch { } }
        }
    }

    (bool, string) Handle(string type, JsonNode? p)
    {
        switch (type)
        {
            case "lock": _lock.Lock(p?["message"]?.GetValue<string>() ?? ""); return (true, "layar dikunci");
            case "unlock": _lock.Unlock(); return (true, "kunci dibuka");
            case "message": new MessageForm(Trunc(p?["text"]?.GetValue<string>()), p?["show_ok"]?.GetValue<bool>() ?? true).Show(); return (true, "pesan ditampilkan");
            case "open_url": return OpenUrl(p?["url"]?.GetValue<string>() ?? "", p?["browser"]?.GetValue<string>() ?? "default", p?["kiosk"]?.GetValue<bool>() ?? false);
            case "run_app": return RunApp(p?["path"]?.GetValue<string>() ?? "", p?["args"]?.GetValue<string>() ?? "");
            default: return (false, "tipe tidak dikenal");
        }
    }
    static string Trunc(string? s) => (s ?? "").Length > 500 ? s![..500] : s ?? "";

    static string? Find(string rel)
    {
        foreach (var root in new[] { Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86), Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData) })
        { var f = Path.Combine(root, rel); if (File.Exists(f)) return f; }
        return null;
    }
    static (bool, string) OpenUrl(string url, string browser, bool kiosk)
    {
        if (!Uri.TryCreate(url, UriKind.Absolute, out var u) || u.Scheme is not ("http" or "https")) return (false, "URL tidak valid");
        var exe = browser switch { "chrome" => Find(@"Google\Chrome\Application\chrome.exe"), "edge" => Find(@"Microsoft\Edge\Application\msedge.exe"), _ => null };
        if (exe == null) { Process.Start(new ProcessStartInfo(u.AbsoluteUri) { UseShellExecute = true }); return (true, browser == "default" ? "dibuka di browser default" : $"{browser} tidak ditemukan, memakai browser default (tanpa kiosk)"); }
        var args = kiosk ? (browser == "edge" ? $"--kiosk \"{u.AbsoluteUri}\" --edge-kiosk-type=fullscreen" : $"--kiosk --new-window \"{u.AbsoluteUri}\"") : $"--new-window \"{u.AbsoluteUri}\"";
        Process.Start(new ProcessStartInfo(exe, args) { UseShellExecute = false });
        return (true, $"dibuka di {browser}{(kiosk ? " (kiosk)" : "")}");
    }
    static (bool, string) RunApp(string path, string args)
    {
        if (!Path.IsPathFullyQualified(path)) // nama saja (mis. calc.exe): hanya dicari di folder Windows
        {
            var win = Environment.GetFolderPath(Environment.SpecialFolder.Windows);
            path = new[] { Path.Combine(win, path), Path.Combine(win, "System32", path) }.FirstOrDefault(File.Exists) ?? "";
        }
        if (path == "" || !File.Exists(path)) return (false, "file program tidak ditemukan di PC ini");
        Process.Start(new ProcessStartInfo(path, args) { UseShellExecute = false });
        return (true, "dijalankan: " + Path.GetFileName(path));
    }
}
