import os
import re

directory = r'd:\projects\rafik\DentalCare\app\Filament\Resources'

# We want to find Forms\Components\Select::make('practice_id') and append ->hidden() to its method chain
# It might look like:
# Forms\Components\Select::make('practice_id')
#     ->relationship('practice', 'name')
#     ->default(...)
#     ->required(),

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Regex to find Select::make('practice_id') block and append ->hidden() just before the next component or closing bracket
    # A bit risky. Let's do something simpler: just add ->hidden() right after Select::make('practice_id')
    # Forms\Components\Select::make('practice_id')->hidden()
    
    new_content = re.sub(
        r"(Forms\\Components\\Select::make\('practice_id'\))",
        r"\1->hidden()",
        content
    )
    
    # Also handle without 'Forms\Components\' prefix if any
    new_content = re.sub(
        r"(?<!Forms\\Components\\)Select::make\('practice_id'\)",
        r"Select::make('practice_id')->hidden()",
        new_content
    )

    if new_content != content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Updated {filepath}")

for root, dirs, files in os.walk(directory):
    for file in files:
        if file.endswith('.php'):
            process_file(os.path.join(root, file))

print("Done.")
