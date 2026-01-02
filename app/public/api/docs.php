<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VeriBits API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *,
        *:before,
        *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background: #0a0a0a;
        }
        .swagger-ui {
            max-width: 1400px;
            margin: 0 auto;
        }
        .topbar-wrapper img {
            content: url('/assets/images/logo.svg');
            height: 40px;
        }
        .swagger-ui .topbar {
            background: #1a1a2e;
            padding: 10px 0;
        }
        .swagger-ui .info .title {
            color: #8b5cf6;
        }
        .swagger-ui .info .description {
            color: #ccc;
        }
        .swagger-ui .opblock-tag {
            color: #fff;
            border-bottom: 1px solid #333;
        }
        .swagger-ui .opblock {
            background: #1a1a2e;
            border: 1px solid #333;
        }
        .swagger-ui .opblock .opblock-summary-method {
            font-weight: bold;
        }
        .swagger-ui .btn.authorize {
            background: #8b5cf6;
            border-color: #8b5cf6;
            color: #fff;
        }
        .swagger-ui .btn.authorize:hover {
            background: #7c3aed;
        }
        /* Custom header */
        .custom-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #333;
        }
        .custom-header h1 {
            color: #8b5cf6;
            margin: 0 0 10px 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .custom-header p {
            color: #888;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .custom-header a {
            color: #8b5cf6;
            text-decoration: none;
        }
        .custom-header a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="custom-header">
        <h1>VeriBits API</h1>
        <p>
            Security and verification tools API |
            <a href="https://veribits.com">Home</a> |
            <a href="https://veribits.com/dashboard">Dashboard</a> |
            <a href="/api/v1/openapi.json">OpenAPI Spec</a>
        </p>
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "/api/v1/openapi.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                persistAuthorization: true,
                displayRequestDuration: true,
                filter: true,
                tryItOutEnabled: true,
                requestSnippetsEnabled: true,
                syntaxHighlight: {
                    activate: true,
                    theme: "monokai"
                }
            });
            window.ui = ui;
        };
    </script>
</body>
</html>
