# FashionDB — Local Development

Quick guide to run and test the project locally (XAMPP on Windows).

1) Database
- Create `fashiondb` and run `migrate.php` from CLI if needed:

```powershell
E:\xampp\php\php.exe -f "E:\xampp\htdocs\CSE 370 PROJECT\migrate.php"
```

2) PHP server
- Use Apache (XAMPP) or the built-in PHP server:

```powershell
E:\xampp\php\php.exe -S localhost:8000 -t "E:\xampp\htdocs\CSE 370 PROJECT"
```

3) Stripe (optional)
- To enable real checkout, set environment variables or edit `stripe_config.php`.

If not set, the app uses the existing mock payment flow.

4) Uploads
- Uploaded files are stored in `uploads/` under the project root. Thumbnails are created automatically.

5) Security
- CSRF tokens are enabled for major forms. Keep PHP sessions working in your environment.

6) Smoke tests
- A simple smoke-test skeleton is available as `smoke_test.php` (edit before running). It uses curl and is intended to run locally.

7) Next steps
- Integrate a payment provider (Stripe done in test-mode scaffold). Provide keys to fully enable checkout.

If you want, I can integrate Stripe Checkout fully and wire the client redirect — tell me and provide test keys or set them in env.
