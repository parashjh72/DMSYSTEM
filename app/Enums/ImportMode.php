<?php

namespace App\Enums;

enum ImportMode: string
{
    /** Insert rows whose IMEI is new; count existing IMEIs as skipped. */
    case InsertNew = 'insert_new';

    /** Alias of InsertNew, phrased for the "don't touch what's there" intent. */
    case SkipExisting = 'skip_existing';

    /** Update mapped columns on existing IMEIs; count new IMEIs as skipped. */
    case UpdateExisting = 'update_existing';

    /** Insert new IMEIs and update existing ones. */
    case Upsert = 'upsert';

    public function insertsNew(): bool
    {
        return in_array($this, [self::InsertNew, self::SkipExisting, self::Upsert], true);
    }

    public function updatesExisting(): bool
    {
        return in_array($this, [self::UpdateExisting, self::Upsert], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::InsertNew => 'Insert new records only',
            self::SkipExisting => 'Skip existing IMEIs',
            self::UpdateExisting => 'Update existing IMEIs only',
            self::Upsert => 'Insert new and update existing',
        };
    }
}
