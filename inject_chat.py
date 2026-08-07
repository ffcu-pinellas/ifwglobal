import os
import re

directory = 'C:/Users/USER/Downloads/IFW-recovery-website - Copy'

injection_code = "\n<?php require_once $dir . '/includes/chat_widget.php'; ?>\n"

modified_count = 0

for root, dirs, files in os.walk(directory):
    # Skip admin, api, includes
    if 'admin' in root or 'api' in root or 'includes' in root:
        continue
        
    for file in files:
        if file.endswith('.php'):
            filepath = os.path.join(root, file)
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
                
            # Check if it's already injected
            if "includes/chat_widget.php" in content:
                continue
                
            # Check if $dir is defined at the top
            if "$dir" not in content:
                continue
                
            # Replace </body> with the injection code + </body>
            if '</body>' in content:
                new_content = content.replace('</body>', injection_code + '</body>')
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                modified_count += 1

print(f"Injected chat widget into {modified_count} files.")
