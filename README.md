# WP SiteAdvisor Pro

Premium extension for WP SiteAdvisor -- adds GPT-4 deep analysis, WPScan vulnerability scanning, PageSpeed auditing, white-label PDF/HTML reports, and a remote license management system.

## Tech Stack

| Layer | Technologies |
|-------|-------------|
| Platform | WordPress 5.0+, PHP 7.4+ |
| AI | OpenAI GPT-4 |
| Security | WPScan API |
| Performance | Google PageSpeed Insights API |
| Reports | PDF and HTML generation |
| Licensing | Custom REST API (remote validation, 48-hour grace period) |

## Code Tour

| Path | What to look at |
|------|----------------|
| `includes/class-license-manager.php` | Remote license activation, validation, grace-period logic |
| `includes/class-ai-analyzer.php` | GPT-4 analysis pipeline -- prompt construction, response parsing, history storage |
| `includes/class-vulnerability-scanner.php` | WPScan API integration for core, plugin, and theme CVE lookups |
| `includes/class-report-generator.php` | Builds branded PDF/HTML reports with security scores and metrics |
| `includes/class-pro-features.php` | Feature registry -- gates each Pro capability behind license status |
| `includes/class-unified-settings.php` | Merged settings page for free + Pro options |
| `includes/class-ai-dashboard.php` | Pro admin dashboard with analysis cards and vulnerability summary |
| `includes/class-ai-settings.php` | AI-specific settings (model selection, token limits, prompt tuning) |
| `includes/features/ai-site-detective.php` | Full-site technology and configuration fingerprinting |
| `includes/features/ai-content-analyzer.php` | Content quality and SEO analysis via AI |
| `includes/features/ai-predictive-analytics.php` | Trend detection and maintenance forecasting |
| `includes/features/pagespeed-analysis.php` | Google PageSpeed Insights integration and scoring |
| `includes/features/white-label-reports.php` | Custom branding for generated reports |
| `includes/features/wpscan-vulnerabilities.php` | WPScan vulnerability enrichment and severity mapping |
| `wp-site-advisory-pro.php` | Bootstrap -- hooks, constants, free-version compatibility check |

## Project Structure

```
wp-site-advisory-pro/
├── includes/
│   ├── class-*.php        # Core Pro classes (license, AI, vulns, reports, settings)
│   └── features/          # Modular Pro features (PageSpeed, WPScan, white-label, predictive)
├── assets/css/            # Admin stylesheet
├── assets/js/             # Admin JavaScript (AJAX handlers, UI)
├── assets/images/         # Branding assets
└── wp-site-advisory-pro.php  # Main plugin entry point
```

## Notes

This repo is sanitized for public viewing. API keys, license secrets, and service credentials are loaded via `get_option()` at runtime and are not stored in source. The plugin is not intended to be run from this repo; it is here to demonstrate code quality and architecture.