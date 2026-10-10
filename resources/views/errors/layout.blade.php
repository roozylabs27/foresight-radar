<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - Foresight Radar</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: Figtree, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f5f7fa;
            color: #1f2937;
            min-height: 100vh;
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        header {
            height: 64px;
            width: 100%;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, #1677ff 0%, #0958d9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(22, 119, 255, 0.35);
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
        }
        .brand-text-title {
            font-size: 15px;
            font-weight: 700;
            color: #001529;
            line-height: 1.2;
            display: block;
        }
        .brand-text-sub {
            font-size: 11px;
            color: #8c8c8c;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        main {
            flex: 1;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .error-card {
            background: #ffffff;
            border: 1px solid #ebeef5;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border-radius: 12px;
            max-width: 580px;
            width: 100%;
            margin: 0 auto;
            padding: 40px 32px;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            font-size: 14px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 16px;
            letter-spacing: 0.5px;
        }
        .status-403 { background: #fffbe6; color: #d46b08; border: 1px solid #ffe58f; }
        .status-404 { background: #fff1f0; color: #cf1322; border: 1px solid #ffa39e; }
        .status-419 { background: #fffbe6; color: #d46b08; border: 1px solid #ffe58f; }
        .status-500 { background: #fff1f0; color: #cf1322; border: 1px solid #ffa39e; }
        .status-503 { background: #e6f4ff; color: #0958d9; border: 1px solid #91caff; }
        .error-title {
            font-size: 24px;
            font-weight: 700;
            color: #1f1f1f;
            margin-bottom: 12px;
        }
        .error-description {
            font-size: 14px;
            color: #595959;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 140px;
        }
        .btn-primary {
            background-color: #1677ff;
            color: #ffffff;
            border: 1px solid #1677ff;
        }
        .btn-primary:hover {
            background-color: #4096ff;
            border-color: #4096ff;
        }
        .btn-default {
            background-color: #ffffff;
            color: #262626;
            border: 1px solid #d9d9d9;
        }
        .btn-default:hover {
            border-color: #1677ff;
            color: #1677ff;
        }
        .error-footer-info {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
            font-size: 12px;
            color: #8c8c8c;
        }
        footer {
            height: 48px;
            background: #ffffff;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #8c8c8c;
            padding: 0 24px;
        }
    </style>
</head>
<body>
    <header>
        <a href="{{ url('/') }}" class="brand">
            <div class="brand-icon">FR</div>
            <div>
                <span class="brand-text-title">Foresight Radar</span>
                <span class="brand-text-sub">Strategic Intel</span>
            </div>
        </a>
        <div>
            @auth
                <a href="{{ route('dashboard.') }}" class="btn btn-default" style="min-width: auto; padding: 6px 14px;">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary" style="min-width: auto; padding: 6px 14px;">Masuk</a>
            @endauth
        </div>
    </header>

    <main>
        <div class="error-card">
            @yield('content')
            <div class="error-footer-info">
                Kode Status HTTP: <strong>@yield('code')</strong> | Foresight Radar Strategic Intelligence System
            </div>
        </div>
    </main>

    <footer>
        Foresight Radar &copy; {{ date('Y') }} Strategic Horizon Scanning. All Rights Reserved.
    </footer>
</body>
</html>
