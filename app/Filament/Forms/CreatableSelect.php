<?php

namespace App\Filament\Forms;

use App\Services\PracticeOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Get;
use Filament\Forms\Set;

/**
 * Phase 5 — dropdowns a clinic can add to.
 *
 *   CreatableSelect::make('medication_name', 'medication')
 *
 * Tap to pick from the clinic's list (most used first), or type and choose
 * "Add …". The chosen text is stored as-is in the column and counted, so it
 * comes up first next time. Values that aren't in the list are still shown.
 */
class CreatableSelect
{
    public static function make(string $name, string $list): Select
    {
        return Select::make($name)
            ->searchable()
            ->options(fn () => app(PracticeOptions::class)->options($list))
            ->getSearchResultsUsing(fn (string $search) => app(PracticeOptions::class)->search($list, $search))
            ->getOptionLabelUsing(fn ($value) => PracticeOptions::defaults($list)[$value] ?? $value)
            ->searchPrompt(__('Type to search, or type something new'))
            ->noSearchResultsMessage(__('Nothing found'))
            ->dehydrateStateUsing(function ($state) use ($list) {
                if (is_string($state) && trim($state) !== '') {
                    $state = PracticeOptions::clean($state);
                    app(PracticeOptions::class)->record($list, $state);
                }

                return $state;
            });
    }

    /**
     * Many values (tags) with the clinic's list as suggestions; new tags are
     * added to the list when the form is saved.
     */
    public static function tags(string $name, string $list): TagsInput
    {
        return TagsInput::make($name)
            ->suggestions(fn () => array_keys(app(PracticeOptions::class)->options($list, null, 100)))
            // Replaces TagsInput's own dehydration, so its separator() join is redone here.
            ->dehydrateStateUsing(function (TagsInput $component, $state) use ($list) {
                $state = is_array($state) ? array_values(array_filter(array_map(fn ($v) => PracticeOptions::clean((string) $v), $state))) : $state;

                if (is_array($state) && $state !== []) {
                    app(PracticeOptions::class)->record($list, $state);
                }

                if (is_array($state) && ($separator = $component->getSeparator())) {
                    return implode($separator, $state);
                }

                return $state;
            });
    }

    /**
     * One-tap phrases for a free-text note: picking a phrase appends it to
     * `$target` (a Textarea next to it). New phrases are remembered.
     */
    public static function phrases(string $name, string $list, string $target): Select
    {
        return Select::make($name)
            ->label(__('Quick phrase'))
            ->placeholder(__('Tap a phrase to add it to the note'))
            ->searchable()
            ->options(fn () => app(PracticeOptions::class)->options($list, null, 30))
            ->getSearchResultsUsing(fn (string $search) => app(PracticeOptions::class)->search($list, $search))
            ->getOptionLabelUsing(fn ($value) => $value)
            ->live()
            ->dehydrated(false)
            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($list, $target, $name) {
                if ($state === null || trim($state) === '') {
                    return;
                }
                $phrase = PracticeOptions::clean($state);
                $current = trim((string) $get($target));
                $set($target, $current === '' ? $phrase : $current.(str_ends_with($current, '.') ? ' ' : '. ').$phrase);
                // Clear the picker so another (or the same) phrase can be tapped next.
                $set($name, null);
                app(PracticeOptions::class)->record($list, $phrase);
            });
    }
}
