# Documentation Images

Screenshots used by the Markdown files in `docs/` live in this folder.

## Naming convention

```
<doc-name>-<subject>[-<detail>].png
```

- **`<doc-name>`** is the Markdown file the image belongs to, without `.md` (for example `user-guide`, `account-settings`, `mcp`). Images then sort together by page.
- **`<subject>`** is the screen or feature shown (for example `dashboard`, `holding-page`, `2fa-setup`).
- **`<detail>`** is optional and describes a state or variant (for example `-dark`, `-empty`, `-error`).
- Use lowercase letters, numbers, and hyphens only. No spaces, underscores, or dates.
- Use `.png` for screenshots. Use `.gif` only for short animations.
- An image used on more than one page keeps the name of the page it was first written for (for example `account-settings-authorized-apps.png` is also used in `mcp.md`). Don't make duplicate copies.

Examples: `user-guide-dashboard.png`, `user-guide-transaction-create.png`, `account-settings-2fa-setup.png`, `troubleshooting-broken-styling.png`.

## Adding a screenshot

Every spot that needs a screenshot has a placeholder made of two HTML comments, so nothing shows on the rendered page until the image exists:

```markdown
<!-- SCREENSHOT: What to capture. -->
<!-- ![Alt text](images/user-guide-dashboard.png) -->
```

To fill one in:

1. Capture the screen described in the `SCREENSHOT` comment.
2. Save it here using the file name from the second line.
3. Delete the `SCREENSHOT` comment line.
4. Remove the `<!--` and `-->` around the image line so it reads `![Alt text](images/user-guide-dashboard.png)`.

To list every placeholder still waiting for an image, run this from the repository root:

```bash
grep -rn "SCREENSHOT:" docs
```

## Capture guidelines

- **Use demo data.** Never include real portfolios, balances, email addresses, API tokens, 2FA QR codes, or setup keys. Blur or crop anything sensitive.
- **Use light mode** by default so screenshots look consistent. Add a `-dark` variant only when it's useful.
- **Use a consistent browser width** of about 1440 px, and crop to the part of the screen that matters.
- **Keep files small.** Aim for under 500 KB; run PNGs through a compressor such as `pngquant` or TinyPNG.
- **Keep alt text meaningful.** Describe what the image shows, not "screenshot".
