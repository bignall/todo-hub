# REST API

The hub's API is at `/wp-json/todo-hub/v1`, or `/?rest_route=/todo-hub/v1` on sites without pretty permalinks. Connected sites use it, and you can too: from scripts, automation tools or another app.

## Logging in

Use an application password (from **Users → Profile**) with HTTP Basic authentication, over https. A user with the **To-do Connector** role can work with tasks and nothing else.

```bash
curl -u 'connector:xxxx xxxx xxxx xxxx xxxx xxxx' https://todo.example.com/wp-json/todo-hub/v1/tasks
```

## Routes

| Method and path | What it does |
|---|---|
| `GET /ping` | Checks the connection. |
| `GET /tasks` | Open tasks, first to do first. Takes `?status=done`, `site`, `key`, `source`, `category` and `per_page`. |
| `POST /tasks` | Adds a task, with the fields of `todo_hub_add()` plus `site` and `callback`. An open task with the same `site` and `key` is updated instead. |
| `GET /tasks/{id}` | Reads a task. |
| `PATCH /tasks/{id}` | Changes a task: only the fields you send. |
| `DELETE /tasks/{id}` | Moves a task to the trash. |
| `POST /tasks/{id}/complete` | Marks a task done. |
| `POST /tasks/{id}/reopen` | Opens a task again. |

```bash
curl -u 'connector:xxxx xxxx xxxx xxxx xxxx xxxx' \
  -H 'Content-Type: application/json' \
  -d '{"title":"Renew the SSL certificate","due":"2026-12-01","source":"my-script","key":"ssl:example.com"}' \
  https://todo.example.com/wp-json/todo-hub/v1/tasks
```

## Callbacks

When a task added with `notify` set and a `callback` address is completed or reopened, the hub POSTs this to the callback:

```json
{ "event": "completed", "task_id": 12, "site": "https://example.com", "key": "my-plugin:renew-ssl" }
```

If that fails, it tries again for about 14 hours. The message carries no secret, so treat it as a nudge: fetch the task back from the hub (`GET /tasks/12`) to see what really happened before acting on it. Connected sites do this for you.
