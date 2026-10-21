using RcmAgent;
var b = Host.CreateApplicationBuilder(new HostApplicationBuilderSettings { Args = args, ContentRootPath = AppContext.BaseDirectory });
b.Services.AddWindowsService(o => o.ServiceName = "RcmAgent");
b.Services.Configure<AgentOptions>(b.Configuration.GetSection("Agent"));
b.Services.AddSingleton<TokenStore>();
b.Services.AddSingleton<ApiClient>();
b.Services.AddSingleton<TaskRunner>();
b.Services.AddHostedService<Worker>();
b.Build().Run();
