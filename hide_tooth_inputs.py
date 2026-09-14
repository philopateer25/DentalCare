import os
import re

directory = r'd:\projects\rafik\DentalCare\app\Filament\Resources'

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Find TextInput::make('tooth_number') or TextInput::make('tooth_number_fdi')
    # and append ->hidden(fn () => \Filament\Facades\Filament::getTenant()?->type === 'ophthalmology')
    
    # Also handle TextColumn::make('tooth_number') or ('tooth_number_fdi')
    
    # We will use regex to find: (TextInput::make\('tooth_number(?:_fdi)?'\))
    # But wait, there might be labels attached, like ->label('Tooth Number')
    # Since these are fluent interfaces, we can just insert ->hidden(...) right after the ::make call.
    
    # Let's see if there is already a hidden() call. If there is, we skip or append to it (too hard).
    # Actually, some might not have ->hidden().
    
    replacements = 0
    
    # Replace TextInput
    new_content, count = re.subn(
        r"(Forms\\Components\\TextInput::make\('tooth_number(?:_fdi)?'\))",
        r"\1->hidden(fn () => \Filament\Facades\Filament::getTenant()?->type === 'ophthalmology')",
        content
    )
    replacements += count
    
    new_content, count = re.subn(
        r"(?<!Forms\\Components\\)(TextInput::make\('tooth_number(?:_fdi)?'\))",
        r"\1->hidden(fn () => \Filament\Facades\Filament::getTenant()?->type === 'ophthalmology')",
        new_content
    )
    replacements += count
    
    # Replace TextColumn
    new_content, count = re.subn(
        r"(Tables\\Columns\\TextColumn::make\('tooth_number(?:_fdi)?'\))",
        r"\1->hidden(fn () => \Filament\Facades\Filament::getTenant()?->type === 'ophthalmology')",
        new_content
    )
    replacements += count
    
    new_content, count = re.subn(
        r"(?<!Tables\\Columns\\)(TextColumn::make\('tooth_number(?:_fdi)?'\))",
        r"\1->hidden(fn () => \Filament\Facades\Filament::getTenant()?->type === 'ophthalmology')",
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
