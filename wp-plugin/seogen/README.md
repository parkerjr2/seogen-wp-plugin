# Hyper Local WordPress Plugin (Skeleton)

## Install

1. In this repo, zip the `seogen/` folder located at `wp-plugin/seogen/`.
2. In WordPress admin, go to **Plugins -> Add New -> Upload Plugin**.
3. Upload the zip and activate **Hyper Local**.

## Settings

1. Go to **Hyper Local** in the WordPress admin menu.
2. Set:
   - **API Base URL** (default: `https://seogen-production.up.railway.app`)
   - **License Key**
3. Click **Save Changes**.

Settings are stored in a single WordPress option named `seogen_settings` with:

- `api_url`
- `license_key`

## Status

The settings page displays two status lines.

- **License Key** shows whether a value is set.
- **API Connection** calls `{api_url}/health` using `wp_remote_get()`.
  - If the endpoint returns HTTP `200` and JSON `{ "status": "ok" }`, the UI shows **Connected**.
  - Otherwise it shows **Not Connected** with a brief error.

## Notes

This is the Phase 4 skeleton only.

- No page generation
- No content creation
- No licensing enforcement beyond the `/health` connectivity check
