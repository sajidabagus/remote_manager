using System.Media;
using System.Runtime.InteropServices;
namespace RcmLauncher;

class LockManager
{
    readonly List<LockForm> _forms = new(); KeyboardHook? _hook; System.Windows.Forms.Timer? _timer;
    public void Lock(string msg)
    {
        Unlock();
        foreach (var s in Screen.AllScreens) { var f = new LockForm(s, msg); _forms.Add(f); f.Show(); }
        _hook = new KeyboardHook();
        _timer = new System.Windows.Forms.Timer { Interval = 500 }; // pertahankan di depan
        _timer.Tick += (_, _) => { foreach (var f in _forms) { f.TopMost = true; f.Activate(); } };
        _timer.Start();
    }
    public void Unlock()
    {
        _timer?.Stop(); _timer?.Dispose(); _timer = null; _hook?.Dispose(); _hook = null;
        foreach (var f in _forms) { f.AllowClose = true; f.Close(); } _forms.Clear();
    }
}

class LockForm : Form
{
    public bool AllowClose;
    public LockForm(Screen s, string msg)
    {
        FormBorderStyle = FormBorderStyle.None; StartPosition = FormStartPosition.Manual; Bounds = s.Bounds;
        TopMost = true; ShowInTaskbar = false; BackColor = Color.FromArgb(11, 31, 77);
        Controls.Add(new Label { Dock = DockStyle.Fill, TextAlign = ContentAlignment.MiddleCenter, ForeColor = Color.White, Font = new Font("Segoe UI", 26), Padding = new Padding(80),
            Text = "Komputer Dikunci" + (msg.Length > 0 ? "\n\n" + msg : "") });
    }
    protected override void OnFormClosing(FormClosingEventArgs e) { if (!AllowClose && e.CloseReason != CloseReason.WindowsShutDown) e.Cancel = true; base.OnFormClosing(e); }
}

class MessageForm : Form
{
    public MessageForm(string text, bool showOk)
    {
        Text = "Pesan dari Admin"; TopMost = true; StartPosition = FormStartPosition.CenterScreen; ClientSize = new Size(520, 240);
        FormBorderStyle = FormBorderStyle.FixedDialog; MaximizeBox = MinimizeBox = false; ControlBox = !showOk; ShowInTaskbar = true;
        Controls.Add(new Label { Text = text, Dock = DockStyle.Fill, Font = new Font("Segoe UI", 13), Padding = new Padding(24), TextAlign = ContentAlignment.MiddleCenter });
        if (showOk)
        {
            var ok = new Button { Text = "OK", Dock = DockStyle.Bottom, Height = 44, Font = new Font("Segoe UI", 11) }; ok.Click += (_, _) => Close(); Controls.Add(ok); AcceptButton = ok;
        }
        else { var t = new System.Windows.Forms.Timer { Interval = 15000 }; t.Tick += (_, _) => Close(); t.Start(); }
        Shown += (_, _) => { SystemSounds.Asterisk.Play(); Activate(); };
    }
}

// Memblokir Win, Alt+Tab, Alt+Esc, Ctrl+Esc, Alt+F4 saat terkunci. Ctrl+Alt+Del tidak bisa diblokir dari aplikasi biasa.
sealed class KeyboardHook : IDisposable
{
    delegate IntPtr Proc(int n, IntPtr w, IntPtr l); readonly Proc _p; IntPtr _h;
    [DllImport("user32")] static extern IntPtr SetWindowsHookEx(int id, Proc p, IntPtr m, uint t);
    [DllImport("user32")] static extern bool UnhookWindowsHookEx(IntPtr h);
    [DllImport("user32")] static extern IntPtr CallNextHookEx(IntPtr h, int n, IntPtr w, IntPtr l);
    [DllImport("user32")] static extern short GetAsyncKeyState(int k);
    [DllImport("kernel32")] static extern IntPtr GetModuleHandle(string? n);
    public KeyboardHook() { _p = Cb; _h = SetWindowsHookEx(13, _p, GetModuleHandle(null), 0); }
    IntPtr Cb(int n, IntPtr w, IntPtr l)
    {
        if (n >= 0)
        {
            int vk = Marshal.ReadInt32(l); bool alt = (Marshal.ReadInt32(l, 8) & 0x20) != 0; bool ctrl = (GetAsyncKeyState(0x11) & 0x8000) != 0;
            if (vk is 0x5B or 0x5C or 0x5D || (alt && vk is 0x09 or 0x1B or 0x73) || (ctrl && vk == 0x1B)) return 1;
        }
        return CallNextHookEx(_h, n, w, l);
    }
    public void Dispose() { if (_h != IntPtr.Zero) { UnhookWindowsHookEx(_h); _h = IntPtr.Zero; } }
}
