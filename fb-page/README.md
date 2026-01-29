# Post to Facebook Page from PHP

This small example shows how to post as a Facebook Page using PHP and the Graph API (via cURL). It includes two scripts:

- `fb_post_page.php` — post a text message to the Page's feed.
- `fb_upload_video.php` — upload a local video file to the Page.
- `fb_config.php` — configuration file with placeholders for tokens and IDs.

Important: Facebook permissions and tokens are required. Posting as a Page requires a Page Access Token that has publishing rights. For production use you will need the correct permissions granted and likely App Review.

Steps to obtain tokens (summary)

1. Create a Facebook App on developers.facebook.com.
2. In your app, request the following permissions during login for the admin user:
   - `pages_manage_posts` (to publish content to Pages)
   - `pages_read_engagement` (to read Page data if needed)
3. Generate a User Access Token for your admin user with those permissions (use Graph API Explorer or your own login flow).
4. Exchange the short-lived user token for a long-lived token using:

```
GET https://graph.facebook.com/oauth/access_token?grant_type=fb_exchange_token&client_id={app-id}&client_secret={app-secret}&fb_exchange_token={short-lived-user-token}
```

5. Use the long-lived user token to get the Page Access Token(s):

```
GET https://graph.facebook.com/me/accounts?access_token={long-lived-user-token}
```

Find the entry for your Page and copy its `access_token`. That is the Page Access Token that the scripts need.

Notes

- App Review: To post to Pages other than those owned by your app's administrators/testers, your app needs to be reviewed and approved by Facebook for the required permissions.
- Tokens expire: Page tokens derived from a long-lived user token can be long-lived, but monitor and refresh as needed.
- Use the Page Access Token only on trusted servers. Do NOT embed in client-side or public repositories.

Running the scripts (PowerShell example)

1. Edit `fb_config.php` and fill in `app_id`, `app_secret`, `user_access_token` (if you use it), `page_id`, and `page_access_token`.

2. Post a text message:

```powershell
php .\fb_post_page.php "Hello from PHP as Page"
```

3. Upload a local video (give absolute path):

```powershell
php .\fb_upload_video.php "C:\\path\\to\\video.mp4" "My video description"
```

Troubleshooting

- If the Graph API returns permission errors, check that the token has `pages_manage_posts` and that the Page token is valid.
- Inspect the JSON response for `error` details. Common issues are expired tokens or missing permissions.
- For large videos you might need to use the resumable upload API (`/video` upload session). This example demonstrates simple direct upload for smaller files.

Security & production notes

- Never commit `fb_config.php` with real tokens to a public repo.
- Store secrets in environment variables or a protected vault in production.
- Consider using the official Facebook PHP SDK for deeper integrations and token management.
