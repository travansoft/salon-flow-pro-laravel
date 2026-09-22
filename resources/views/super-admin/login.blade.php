<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} — SalonFlow Pro Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Bricolage+Grotesque:opsz,wght@12..96,400..800&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #16201D;
            color: #16201D;
            font-family: Outfit, system-ui, sans-serif;
        }

        .panel {
            width: 100%;
            max-width: 392px;
            background: #fff;
            border-radius: 20px;
            padding: 44px;
            box-sizing: border-box;
        }

        .brand-font {
            font-family: 'Bricolage Grotesque', Outfit, sans-serif;
        }

        .login-form-input {
            width: 100%;
            padding: 15px 16px;
            border: 1px solid #E4D8D1;
            border-radius: 14px;
            background: #fff;
            font-size: 15px;
            font-family: inherit;
            margin-bottom: 18px;
            outline: none;
            box-sizing: border-box;
        }

        .login-submit {
            width: 100%;
            border: none;
            background: #16201D;
            color: #fff;
            padding: 16px;
            border-radius: 14px;
            font-size: 15.5px;
            font-family: inherit;
            cursor: pointer;
        }

        .login-error {
            background: #FBEAEA;
            border: 1px solid #E7B9B9;
            color: #8A2C2C;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            margin-bottom: 18px;
        }
    </style>
</head>
<body>
    <div class="panel">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:32px">
            <div class="brand-font" style="width:30px;height:30px;border-radius:10px;background:#16201D;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:18px;color:#fff">S</div>
            <span class="brand-font" style="font-weight:600;font-size:18px">SalonFlow <span>Pro</span> Platform</span>
        </div>

        <h1 class="brand-font" style="font-weight:600;font-size:28px;letter-spacing:-.02em;margin:0 0 8px">{{ __('Super admin sign in') }}</h1>
        <p style="font-size:14px;color:#66736F;margin:0 0 28px">{{ __('Manage tenants and platform accounts.') }}</p>

        @if ($errors->any())
            <div class="login-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ $superAdminUrl->route('superAdmin.login.store') }}">
            @csrf

            <label style="display:block;font-size:12.5px;letter-spacing:.05em;text-transform:uppercase;color:#788582;margin-bottom:8px">{{ __('Username') }}</label>
            <input type="text" name="username" value="{{ old('username') }}" autofocus class="login-form-input">

            <label style="display:block;font-size:12.5px;letter-spacing:.05em;text-transform:uppercase;color:#788582;margin-bottom:8px">{{ __('Password') }}</label>
            <input type="password" name="password" class="login-form-input" style="margin-bottom:24px">

            <button type="submit" class="login-submit">{{ __('Sign in') }}</button>
        </form>
    </div>
</body>
</html>
