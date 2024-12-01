# Bluesky Feed Widget

> _Simple function to retrieve Bluesky posts and optionally return as customizable widget_

Until Bluesky releases an official feed widget for external websites, this function is easy to incorporate into LAMP stack projects.

- Accepts your account handle (e.g. georgetakei.bsky.social) and app password (obtain from https://bsky.app/settings/app-passwords), as well as various optional configuration parameters (easily add more)
- Returns either
  - an array with all data so you can build your own widget, or
  - a finished HTML widget which employs Bootstrap (4.x) for clean formatting and mobile adaptiveness, as well as some id and class labels for external CSS customization
- Incorporates a single globally-scoped variable to transit exceptions out of the function where they can be passed to whatever existing error handling code exists in the application
- No dependencies other than cURL, and should be reasonably backwards- and forwards-compatible with common LAMP versions