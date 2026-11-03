# School signup and subscriptions

## Deploy

1. Back up the database, deploy source, and run `php artisan migrate --force`.
2. Run `npm run build`. Ensure public storage is linked with `php artisan storage:link`.
3. Configure a real mail transport using the existing `config/mail.php` settings: `MAIL_MAILER=smtp`, SMTP host/port/credentials, and a valid verified sender address. Set `APP_URL` to the public HTTPS application URL so signed verification links point to the correct host. Never commit mail credentials.
4. Run `php artisan config:cache` and `php artisan subscriptions:check-mail`. Send and open an actual verification email from the deployed site before enabling enforcement for users.
5. `OWNER_EMAIL_VERIFICATION_ENFORCED=true` is the default. Temporarily set it to false during delivery setup, then enable it after the delivery test and clear/rebuild config cache. Production automatically suspends the verification block for log, array, or composite mailers. Transport readiness checks configuration, not actual inbox delivery.

The migration preserves existing school expiry through the end of its previous expiry day, leaves historical contribution attribution unknown, and gives existing unverified owners one hour from migration. Deploy with working mail configured to honor that grace period. All dates are stored in the application's configured timezone; the interface displays the viewer's local timezone.

## Manual renewal

Open **Super Admin → Schools**. Match the school's EB reference/email and check the screenshot received on WhatsApp 03284177318. Use **Manage access** to apply one month for PKR 500 or three months for PKR 1,500. Time starts from the later of now or existing expiry. Trial extensions add the requested weeks from that same base. All changes record the administrator and previous/new expiry. Duplicate requests cannot extend a school twice.

Payment destination for both JazzCash and Easypaisa: **03080440058 — Hafiz Muhammad Azeem**. The application does not accept screenshots, process money, automatically approve payment, or treat opening WhatsApp as a payment.

School owners verify email; school-created teachers and super admins are exempt. An owner changing email gets a fresh one-hour grace period. Super admins can manually verify owners from the Schools screen, with an audit record.

Expired schools can view/print their existing saved papers and manage essential account settings. Creation/editing and other tools are blocked server-side, including direct API calls and diagrams.
