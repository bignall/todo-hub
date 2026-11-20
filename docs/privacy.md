# Privacy and data

## What's stored

- **Tasks** are stored in your site's database, as a private post type. They hold the title, notes, category, priority, due date and time, repeat, link, and the plugin or site that added them.
- **On a connected site:** the hub's address, the user name and the application password, plus any changes still waiting to be sent.
- **With Google Calendar:** your Google Cloud client ID and secret, and the access Google grants. The plugin can only use its own calendar.

## What's sent elsewhere

| Feature | Sent to | What |
|---|---|---|
| Connected sites | Your hub | Each task the connected site or its plugins add or change |
| Completion callbacks | The connected site that added the task | That a task was completed or reopened, with its id and key |
| Google Calendar | Google | For each task with a due date: its title, notes, link and due date and time |

Nothing goes to anyone else. To-do Hub doesn't phone home: if it asks why you're deactivating it, your answer is kept on your own site.

## Uninstalling

Deactivating keeps everything. To remove all of To-do Hub's data when you delete the plugin, first tick **To-do → Settings → When deleting the plugin**. Deleting the plugin then removes all tasks, categories and settings. The events on your Google "To-do" calendar stay in Google; delete that calendar there if you no longer want it.
