<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Swagger extends CI_Controller {

	public function index()
	{
		$this->load->helper('url');
		$this->output->set_content_type('text/html; charset=utf-8');
		$jsonUrl = site_url('swagger/json');
		$html = '<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Swagger UI</title>
	<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
	<style>body{margin:0;} #swagger-ui{margin:0}</style>
</head>
<body>
	<div id="swagger-ui"></div>
	<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
	<script>
		window.addEventListener("load", function() {
			SwaggerUIBundle({
				url: "' . $jsonUrl . '",
				dom_id: "#swagger-ui",
				presets: [SwaggerUIBundle.presets.apis],
				layout: "BaseLayout"
			});
		});
	</script>
</body>
</html>';
		$this->output->set_output($html);
	}

	public function json()
	{
		$this->output->set_content_type('application/json');
		$specPath = APPPATH . 'swagger.json';
		if (is_readable($specPath)) {
			$spec = file_get_contents($specPath);
			$this->output->set_output($spec !== false ? $spec : '{}');
			return;
		}

		$spec = array(
			'openapi' => '3.0.1',
			'info' => array(
				'title' => 'Celoe API',
				'version' => '1.0.0'
			),
			'servers' => array(
				array('url' => '/index.php')
			),
			'paths' => array(
				'/api/analytics/health' => array(
					'get' => array(
						'summary' => 'Health check',
						'responses' => array(
							'200' => array('description' => 'OK')
						)
					)
				)
			)
		);

		$this->output->set_output(json_encode($spec));
	}
} 