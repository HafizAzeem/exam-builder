# Dynamic subscription plans

## Deployment

Run `php artisan migrate --force` before serving the updated application, then `npm run build` (or deploy the built assets). The additive catalog migration seeds a 14-day trial and Monthly (PKR 500 / 1 month), Three months (PKR 1,350 / 3 months), and Annual (PKR 4,500 / 12 months). It does not change existing school expiry dates or payment events.

Existing installations can apply only this migration with:

`php artisan migrate --path=database/migrations/2026_10_27_000001_create_subscription_catalog.php --force`

Run `php artisan seo:build-public` after deploying to update generated discovery text. It refers readers to live pricing rather than freezing prices or trial duration into the summary.

## Administration

Open **Super admin → Plans** (`/super-admin/plans`). Edit English/Urdu names, integer PKR prices, calendar-month durations, display order and availability. Trial duration can be 1–90 days and applies to new registrations only. At least one paid plan must remain active.

Identifiers are permanent; disable retired plans instead of deleting or renaming identifiers. Historical subscription events retain their recorded amount. New activations use the current active plan price and duration. Plan changes do not automatically charge schools or alter access already granted.

The catalog feeds English/Urdu public pricing and structured offers, school renewal, super admin manual activation, registration trials and dashboard labels. Payment numbers remain in `config/subscriptions.php`. Static duplicated plan configuration has been removed.

## Offer

All paid plans include core question-bank and paper-building tools. Schools add their own questions; a complete ready-made syllabus bank is not promised. Public landing/pricing copy does not advertise AI. Testing-only application AI controls are unchanged by this feature.
