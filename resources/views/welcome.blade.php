<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'SalonFlow Pro') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&family=Bricolage+Grotesque:opsz,wght@12..96,500..700&display=swap" rel="stylesheet">

        <style>
            :root {
                --brand-blue: #1B4B8F;
                --brand-blue-dark: #153B70;
                --brand-ink: #16201D;
                --brand-bg: #F3F6F5;
                --brand-muted: #5C6866;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: radial-gradient(circle at 20% 20%, #E9F0EE 0%, var(--brand-bg) 55%);
                color: var(--brand-ink);
                font-family: 'Outfit', system-ui, sans-serif;
                -webkit-font-smoothing: antialiased;
            }

            .brand {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 2rem;
            }

            .brand-mark {
                width: 64px;
                height: 64px;
                border-radius: 18px;
                background: var(--brand-blue);
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Bricolage Grotesque', sans-serif;
                font-weight: 700;
                font-size: 32px;
                color: #fff;
                box-shadow: 0 12px 28px rgba(27, 75, 143, .28);
                margin-bottom: 28px;
            }

            .brand-name {
                font-family: 'Bricolage Grotesque', sans-serif;
                font-weight: 600;
                font-size: clamp(2.5rem, 6vw, 3.75rem);
                letter-spacing: -.03em;
                line-height: 1.05;
                margin: 0;
            }

            .brand-name span {
                color: var(--brand-blue);
            }

            .brand-caption {
                margin: 18px 0 0;
                font-size: clamp(1rem, 2vw, 1.2rem);
                color: var(--brand-muted);
                max-width: 30rem;
                line-height: 1.55;
            }
        </style>
    </head>
    <body>
        <div class="brand">
            <div class="brand-mark">S</div>
            <h1 class="brand-name">SalonFlow <span>Pro</span></h1>
            <p class="brand-caption">Run the floor, not the paperwork.</p>
        </div>
    </body>
</html>
</content>
