<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 Not Found - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
.error-box { text-align: center; padding: 60px 40px; background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,.08); max-width: 560px; width: 100%; }
.error-code { font-size: 7rem; font-weight: 800; color: #0d6efd; line-height: 1; }
.search-box { position: relative; }
.search-box .btn { position: absolute; right: 0; top: 0; bottom: 0; border-radius: 0 .375rem .375rem 0; }
</style>
</head>
<body>
<div class="error-box">
    <div style="font-size:3rem;">🔍</div>
    <div class="error-code">404</div>
    <h2 class="mt-3 mb-2 fw-bold">Page Not Found</h2>
    <p class="text-muted mb-4">The page you're looking for doesn't exist or has been moved. Try searching or navigate using the links below.</p>
    <form action="/index.php" method="get" class="mb-4 search-box d-flex">
        <input type="text" name="q" class="form-control" placeholder="Search the site…">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="/" class="btn btn-primary px-4"><i class="bi bi-house me-2"></i>Homepage</a>
        <a href="/contact.php" class="btn btn-outline-primary px-4"><i class="bi bi-envelope me-2"></i>Contact Us</a>
    </div>
    <div class="mt-4">
        <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="max-height:40px; opacity:.6;">
    </div>
</div>
</body>
</html>
