<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Celoe API Dev - API Documentation</title>
    
    <!-- Swagger UI CSS -->
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
    
    <!-- Custom Styles -->
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        
        .header p {
            margin: 10px 0 0 0;
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .download-links {
            background: #f8f9fa;
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .download-links a {
            display: inline-block;
            margin: 0 10px;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .download-links a:hover {
            background: #0056b3;
        }
        
        .swagger-container {
            padding: 20px;
        }
        
        .swagger-ui .topbar {
            display: none;
        }
        
        .swagger-ui .info .title {
            font-size: 2.5em !important;
            color: #3b4151 !important;
        }
        
        .swagger-ui .info .description {
            font-size: 1.1em !important;
            line-height: 1.6 !important;
        }
        
        .swagger-ui .scheme-container {
            background: #f8f9fa !important;
            border-radius: 4px !important;
            padding: 15px !important;
        }
        
        .swagger-ui .scheme-container .schemes-title {
            font-weight: 600 !important;
            color: #3b4151 !important;
        }
        
        .swagger-ui .auth-wrapper {
            background: #e3f2fd !important;
            border-radius: 4px !important;
            padding: 15px !important;
        }
        
        .swagger-ui .auth-wrapper .authorize {
            background: #2196f3 !important;
            border-color: #2196f3 !important;
            color: white !important;
        }
        
        .swagger-ui .auth-wrapper .authorize:hover {
            background: #1976d2 !important;
        }
        
        .swagger-ui .opblock.opblock-get .opblock-summary-method {
            background: #61affe !important;
        }
        
        .swagger-ui .opblock.opblock-post .opblock-summary-method {
            background: #49cc90 !important;
        }
        
        .swagger-ui .opblock.opblock-put .opblock-summary-method {
            background: #fca130 !important;
        }
        
        .swagger-ui .opblock.opblock-delete .opblock-summary-method {
            background: #f93e3e !important;
        }
        
        .swagger-ui .opblock.opblock-patch .opblock-summary-method {
            background: #50e3c2 !important;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
        }
        
        .footer a {
            color: #007bff;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2em;
            }
            
            .download-links a {
                display: block;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>🚀 Celoe API Dev</h1>
        <p>Comprehensive API Documentation for ETL & Analytics Services</p>
    </div>
    
    <!-- Download Links -->
    <div class="download-links">
        <a href="<?= base_url('swagger/download') ?>" target="_blank">
            📥 Download OpenAPI JSON
        </a>
        <a href="<?= base_url('swagger/yaml') ?>" target="_blank">
            📥 Download OpenAPI YAML
        </a>
        <a href="<?= base_url('swagger/spec') ?>" target="_blank">
            🔗 View Raw Spec
        </a>
    </div>
    
    <!-- Swagger UI Container -->
    <div class="swagger-container">
        <div id="swagger-ui"></div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <p>
            Powered by <a href="https://swagger.io/" target="_blank">Swagger UI</a> | 
            <a href="https://github.com/OAI/OpenAPI-Specification" target="_blank">OpenAPI 3.0</a> | 
            Built with ❤️ by Celoe Development Team
        </p>
    </div>
    
    <!-- Swagger UI JavaScript -->
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
    
    <!-- Initialize Swagger UI -->
    <script>
        window.onload = function() {
            // Initialize Swagger UI
            const ui = SwaggerUIBundle({
                url: '<?= $swagger_url ?>',
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
                validatorUrl: null,
                docExpansion: 'list',
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
                tryItOutEnabled: true,
                requestInterceptor: function(request) {
                    // Add default authorization header if available
                    const token = localStorage.getItem('swagger_token');
                    if (token) {
                        request.headers['Authorization'] = 'Bearer ' + token;
                    }
                    return request;
                },
                onComplete: function() {
                    // Custom completion handler
                    console.log('Swagger UI loaded successfully');
                    
                    // Add custom styling for better mobile experience
                    const style = document.createElement('style');
                    style.textContent = `
                        @media (max-width: 768px) {
                            .swagger-ui .opblock-summary-description {
                                max-width: 100% !important;
                            }
                            .swagger-ui .opblock-summary-path {
                                max-width: 100% !important;
                            }
                        }
                    `;
                    document.head.appendChild(style);
                }
            });
            
            // Store token in localStorage when authorized
            window.addEventListener('message', function(event) {
                if (event.data && event.data.type === 'swagger_authorized') {
                    localStorage.setItem('swagger_token', event.data.token);
                }
            });
        };
    </script>
</body>
</html>

