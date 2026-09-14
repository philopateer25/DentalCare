import os
import re

directory = r'd:\projects\rafik\DentalCare\app\Filament\Resources'

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    replacements = 0
    replacement_str = r"->hidden(fn () => \Filament\Facades\Filament::getTenant()?->type === 'ophthalmology')"
    
    new_content, count = re.subn(
        r"(Forms\\Components\\TextInput::make\('tooth_number(?:_fdi)?'\))",
        lambda m: m.group(1) + replacement_str,
        content
    )
    replacements += count
    
    new_content, count = re.subn(
        r"(?<!Forms\\Components\\)(TextInput::make\('tooth_number(?:_fdi)?'\))",
        lambda m: m.group(1) + replacement_str,
        new_content
    )
    replacements += count
    
    new_content, count = re.subn(
        r"(Tables\\Columns\\TextColumn::make\('tooth_number(?:_fdi)?'\))",
        lambda m: m.group(1) + replacement_str,
        new_content
    )
    replacements += count
    
    new_content, count = re.subn(
        r"(?<!Tables\\Columns\\)(TextColumn::make\('tooth_number(?:_fdi)?'\))",
        lambda m: m.group(1) + replacement_str,
        new_content
    )
    replacements += count
    
    if replacements > 0 and new_content != content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Updated {filepath}")

for root, dirs, files in os.walk(directory):
    for file in files:
        if file.endswith('.php'):
            process_file(os.path.join(root, file))

print("Done.")
