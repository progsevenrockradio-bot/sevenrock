# Upload Limits

Recommended PHP settings for large MP3 uploads:

```ini
upload_max_filesize = 512M
post_max_size = 512M
memory_limit = 768M
max_execution_time = 0
max_input_time = 300
```

Notes:
- Large files should go through the stream-based Backblaze B2 path.
- Small files keep the existing upload flow.
- If the shared host does not allow global `php.ini` changes, use `.user.ini` or the hosting panel.
