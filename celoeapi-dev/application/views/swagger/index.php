<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - CeloeAPI</title>
    
    <!-- Swagger UI CSS -->
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
    
    <!-- Custom CSS -->
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #fafafa;
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
        
        .swagger-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
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
            border-radius: 8px !important;
            padding: 15px !important;
            margin: 20px 0 !important;
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
            text-align: center;
            padding: 20px;
            color: #666;
            border-top: 1px solid #eee;
            margin-top: 40px;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .actions {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 10px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #5a6fd8;
            color: white;
            text-decoration: none;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CeloeAPI Documentation</h1>
        <p>Interactive API documentation for Learning Management System Analytics</p>
    </div>
    
    <div class="swagger-container">
        <div class="actions">
            <a href="<?php echo base_url('swagger/generate'); ?>" class="btn btn-success">🔄 Regenerate Docs</a>
            <a href="<?php echo base_url('swagger/download'); ?>" class="btn btn-secondary">📥 Download JSON</a>
            <a href="<?php echo base_url('api'); ?>" class="btn">🔗 API Base URL</a>
        </div>
        
        <div id="swagger-ui"></div>
    </div>
    
    <div class="footer">
        <p>Powered by <a href="https://swagger.io/" target="_blank">Swagger</a> | 
           CeloeAPI v1.0.0 | 
           <a href="<?php echo base_url(); ?>">Back to Home</a></p>
    </div>
    
    <!-- Swagger UI JavaScript -->
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
    
    <script>
        window.onload = function() {
            // Initialize Swagger UI
            const ui = SwaggerUIBundle({
                url: '<?php echo $swagger_url; ?>',
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
                onComplete: function() {
                    console.log('Swagger UI loaded successfully');
                },
                onFailure: function(data) {
                    console.error('Swagger UI failed to load:', data);
                    document.getElementById('swagger-ui').innerHTML = 
                        '<div style="text-align: center; padding: 40px; color: #666;">' +
                        '<h3>Failed to load API documentation</h3>' +
                        '<p>Please try regenerating the documentation or check the console for errors.</p>' +
                        '<a href="<?php echo base_url("swagger/generate"); ?>" class="btn btn-success">Regenerate Docs</a>' +
                        '</div>';
                }
            });
            
            // Add custom styling
            setTimeout(function() {
                const style = document.createElement('style');
                style.textContent = `
                    .swagger-ui .info .title { font-size: 2.5em !important; }
                    .swagger-ui .info .description { font-size: 1.1em !important; }
                `;
                document.head.appendChild(style);
            }, 1000);
        };
    </script>
</body>
</html>
