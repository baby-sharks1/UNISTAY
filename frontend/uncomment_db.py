import os
import re

def uncomment_file(file_path):
    with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
        lines = f.readlines()
    
    new_lines = []
    i = 0
    while i < len(lines):
        line = lines[i]
        if 'Database query disabled for commit' in line:
            i += 1
            while i < len(lines):
                curr_line = lines[i]
                stripped = curr_line.strip()
                if stripped.startswith('//'):
                    uncommented = re.sub(r'^(\s*)//\s?', r'\1', curr_line)
                    new_lines.append(uncommented)
                    i += 1
                else:
                    break
            continue
        
        stripped = line.strip()
        if stripped.startswith('//') and any(keyword in stripped for keyword in ['$pdo', '$db', 'query', 'prepare', 'execute', 'fetch', 'mysqli_', 'sql', 'insert', 'update', 'delete', 'select']):
            uncommented = re.sub(r'^(\s*)//\s?', r'\1', line)
            new_lines.append(uncommented)
        else:
            new_lines.append(line)
        i += 1
        
    with open(file_path, 'w', encoding='utf-8') as f:
        f.writelines(new_lines)

def process_project(root_dir):
    for subdir, dirs, files in os.walk(root_dir):
        if '.git' in subdir or 'vendor' in subdir or 'node_modules' in subdir:
            continue
        for file in files:
            if file.endswith('.php'):
                file_path = os.path.join(subdir, file)
                try:
                    uncomment_file(file_path)
                    print(f"Processed: {file_path}")
                except Exception as e:
                    print(f"Error processing {file_path}: {e}")

if __name__ == '__main__':
    process_project('.')
    print("Uncommenting completed successfully.")
