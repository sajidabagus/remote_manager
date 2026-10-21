using System.Net;
using Microsoft.Extensions.Options;
namespace RcmAgent;

public class Worker(ILogger<Worker> log, ApiClient api, TokenStore store, TaskRunner runner, IOptions<AgentOptions> opt) : BackgroundService
{
    const string Version = "0.2.0";
    protected override async Task ExecuteAsync(CancellationToken ct)
    {
        var token = store.Load(); int hb = Math.Max(5, opt.Value.HeartbeatSeconds); int delay = hb;
        while (!ct.IsCancellationRequested)
        {
            try
            {
                if (token == null) { token = await Enroll(ct); delay = token == null ? 30 : hb; }
                else { token = await Cycle(token, ct); delay = hb; }
            }
            catch (OperationCanceledException) when (ct.IsCancellationRequested) { break; }
            catch (Exception e) { log.LogWarning("Server tidak terjangkau: {m}", e.Message); delay = 30; }
            try { await Task.Delay(TimeSpan.FromSeconds(delay), ct); } catch (OperationCanceledException) { break; }
        }
    }

    async Task<string?> Enroll(CancellationToken ct)
    {
        var mac = NetInfo.PrimaryMac();
        if (mac == null) { log.LogWarning("MAC adapter utama tidak ditemukan"); return null; }
        var (code, b) = await api.Post("enroll", new { mac, hostname = Environment.MachineName, version = Version }, null, ct);
        var st = b?["status"]?.GetValue<string>();
        if (st == "ok") { var t = b!["token"]!.GetValue<string>(); store.Save(t); log.LogInformation("Terdaftar di server ({mac})", mac); return t; }
        if (st == "pending") log.LogInformation("Menunggu persetujuan admin");
        else if (st == "already_enrolled") log.LogWarning("Perangkat sudah terdaftar di server tetapi token lokal hilang. Minta admin menekan Reset token.");
        else log.LogWarning("Enroll gagal: {c} {s}", (int)code, st);
        return null;
    }

    async Task<string?> Cycle(string token, CancellationToken ct)
    {
        var (code, b) = await api.Post("heartbeat", new { version = Version }, token, ct);
        if (code == HttpStatusCode.Unauthorized) { log.LogWarning("Token ditolak server; mendaftar ulang"); store.Clear(); return null; }
        if (code != HttpStatusCode.OK || (b?["tasks"]?.GetValue<int>() ?? 0) == 0) return token;

        var (_, tb) = await api.Post("tasks/pending", new { }, token, ct);
        foreach (var t in tb?["tasks"]?.AsArray() ?? [])
        {
            var id = t!["id"]!.GetValue<int>(); var type = t["type"]!.GetValue<string>();
            var (ok, msg) = await runner.Run(type, t["payload"], ct);
            await api.Post("tasks/result", new { id, status = ok ? "done" : "failed", message = msg }, token, ct);
        }
        return token;
    }
}
