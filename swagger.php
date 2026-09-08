<!DOCTYPE html>
<html>
  <head>
    <title>Swagger UI CSIMS PHP</title>
    <link rel="stylesheet" type="text/css" href="swagger-ui/swagger-ui.css" />
  </head>
  <body>
    <div id="swagger-ui"></div>
    <script src="swagger-ui/swagger-ui-bundle.js"></script>
    <script>
      const ui = SwaggerUIBundle({
        url: "swagger.json",   // ใช้ไฟล์ swagger.json
        dom_id: '#swagger-ui',
      });
    </script>
  </body>
</html>
