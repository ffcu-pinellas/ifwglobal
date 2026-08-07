import os
import re

directory = "C:/Users/USER/Downloads/IFW-recovery-website - Copy"
injection_code = "<?php require_once __DIR__ . '/includes/announcement.php'; ?>"

exclude_dirs = ['admin', 'client', 'api', 'includes', 'uploads', 'media', 'wp-content', 'wp-includes']

for root, dirs, files in os.walk(directory):
    # Exclude specific directories
    dirs[:] = [d for d in dirs if d not in exclude_dirs]
    
    for file in files:
        if file.endswith('.php') and file != 'config.php' and file != 'upgrade_db.php':
            filepath = os.path.join(root, file)
            with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
            
            if 'includes/announcement.php' not in content:
                # Find body tag, can be <body class="...">
                new_content = re.sub(r'(<body[^>]*>)', r'\1\n' + injection_code, content, count=1, flags=re.IGNORECASE)
                if new_content != content:
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    print(f"Injected announcement into {file}")
