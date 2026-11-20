# Google Calendar

On the hub, To-do Hub can put tasks with a due date on a "To-do" calendar in your Google account:

- Tasks with only a date become **all-day events**.
- Tasks with a time become **events at that time**, 30 minutes long by default.
- Each event's description has the task's notes, its link and a link back to the task.
- When a task is done, its event gets a ✓ in front and shows as free. Or you can have the event removed instead.
- Tasks with no due date, in the Trash or deleted have no event.

It's one way only: changes you make in Google Calendar aren't copied back to the list.

## Setting it up

You use your own Google Cloud project, so your tasks go straight from your site to your Google account.

1. In the [Google Cloud console](https://console.cloud.google.com/), create a project (or pick one) and enable the **Google Calendar API**.
2. Under **APIs & Services → OAuth consent screen**, set up the app for your own account. Then set its publishing status to **In production**. While it's "Testing", Google ends the connection after 7 days. For your own use you don't need Google's verification; you'll see an "unverified app" warning you can continue past.
3. Under **Credentials**, create an **OAuth client ID** of type **Web application**. Add the redirect URI shown in **To-do → Settings → Google Calendar** (it ends in `admin-post.php?action=tdh_google_callback`).
4. Paste the client ID and secret into the settings, save, and press **Connect Google Calendar**.

To-do Hub asks only for the `calendar.app.created` permission. It can create its own calendar and manage the events on it, but it can't see your other calendars.

## Keeping it up to date

Changes go to Google as soon as you make them. Anything Google can't take right away is tried again every five minutes, and once a day everything is checked again. Only events that would look different are sent.

If Google stops accepting the connection (for example, because the app was still "Testing"), the settings page says so: press **Connect** again.
