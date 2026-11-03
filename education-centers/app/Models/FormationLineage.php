<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table de correspondance (historique) entre une ancienne formation-spécialité
 * et la formation générale officielle qui la remplace.
 *
 * Aucune donnée n'est détruite : la formation héritée reste intacte et l'on
 * conserve ici le lien de traçabilité (règle de non-régression historique).
 */
class FormationLineage extends Model
{
    protected $table = 'formation_lineage';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'official_formation_id',
        'legacy_formation_id',
    ];

    /**
     * La formation générale officielle cible.
     */
    public function officialFormation(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'official_formation_id');
    }

    /**
     * L'ancienne formation (spécialité) à l'origine du rattachement.
     */
    public function legacyFormation(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'legacy_formation_id');
    }
}
