<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Center;
use App\Models\Certificate;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\AcademicPerimeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Calcule les indicateurs d'un tableau de bord de formation générale.
 *
 * Logique partagée entre le Super Admin (tous centres, filtre centre optionnel)
 * et l'Admin Centre (restreint à son centre). Le périmètre est exactement le
 * même que celui utilisé par le filtrage des listes et des menus déroulants :
 * l'autorité unique {@see AcademicPerimeter} (formation générale
 * et ses alias historiques) + une éventuelle restriction centre. Aucun chiffre
 * n'est simulé : tout est agrégé depuis la base.
 */
class DomainDashboardData
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $domain, ?int $centerId): array
    {
        $scope = fn (Builder $q): Builder => $this->scopedTo($q, $domain, $centerId);

        $stats = [
            'students' => $scope(Student::query())->count(),
            'teachers' => $scope(Teacher::query())->count(),
            'formations' => $scope(Formation::query())->count(),
            'classes' => $scope(ClassRoom::query())->count(),
            'enrollments' => $scope(Enrollment::query())->count(),
            'pending_enrollments' => $scope(Enrollment::query()->where('status', Enrollment::STATUS_PENDING))->count(),
            'attendances_today' => $scope(Attendance::query()->whereDate('date', now()->toDateString()))->count(),
            'payments_total' => (float) $scope(Payment::query()->where('status', 'paid'))->sum('amount'),
            'certificates_issued' => $scope(Certificate::query()->where('status', 'issued'))->count(),
        ];

        $recentEnrollments = $scope(Enrollment::query()->with(['student', 'formation', 'center']))
            ->latest()
            ->take(8)
            ->get();

        $upcomingSchedules = $scope(
            Schedule::query()
                ->where('date', '>=', now()->toDateString())
                ->where('status', 'scheduled')
                ->with(['classRoom', 'teacher.user', 'room'])
                ->orderBy('date')
                ->orderBy('start_time')
        )->take(5)->get();

        // Série réelle : inscriptions par mois (année courante) dans le périmètre.
        $year = (int) now()->year;
        $yearEnrollments = $scope(Enrollment::query()->whereYear('created_at', $year))->get(['created_at']);
        $enrollmentTrend = collect(range(1, 12))->mapWithKeys(fn (int $m) => [
            $m => $yearEnrollments->filter(fn ($e) => (int) $e->created_at->format('n') === $m)->count(),
        ]);

        // Série réelle : répartition des étudiants (du domaine) par centre.
        $studentsByCenter = $this->studentsByCenter($domain, $centerId);

        return [
            'stats' => $stats,
            'recentEnrollments' => $recentEnrollments,
            'upcomingSchedules' => $upcomingSchedules,
            'year' => $year,
            'enrollmentTrend' => $enrollmentTrend,
            'studentsByCenter' => $studentsByCenter,
        ];
    }

    /**
     * Applique l'espace de formation + le centre (si fourni) à une requête.
     *
     * Passe par l'autorité unique du périmètre
     * {@see AcademicPerimeter} : les chiffres du tableau de bord
     * sont ainsi agrégés sur exactement les mêmes lignes que celles des listes
     * et des menus déroulants du module (anciennes sous-formations comprises,
     * puisqu'elles portent aujourd'hui les données de la formation générale).
     */
    private function scopedTo(Builder $q, string $domain, ?int $centerId): Builder
    {
        AcademicPerimeter::for(request()->user(), $centerId, $domain)->applyTo($q);

        if ($centerId) {
            $q->where($q->qualifyColumn('center_id'), $centerId);
        }

        return $q;
    }

    /**
     * Nombre d'étudiants de la formation regroupés par centre (top 5 + Autres).
     *
     * @return Collection<string, int>
     */
    private function studentsByCenter(string $domain, ?int $centerId): Collection
    {
        $query = AcademicPerimeter::for(request()->user(), $centerId, $domain)
            ->applyTo(Student::query());

        if ($centerId) {
            $query->where('center_id', $centerId);
        }

        $counts = $query->reorder()
            ->selectRaw('center_id, COUNT(*) as aggregate')
            ->groupBy('center_id')
            ->pluck('aggregate', 'center_id');

        $centers = Center::orderByDesc('id')->get()->keyBy('id');

        $labelled = collect();
        foreach ($counts as $cid => $count) {
            $name = $centers[$cid]?->translated('name') ?? __('Centre #:id', ['id' => $cid]);
            $labelled[$name] = (int) $count;
        }

        return $labelled->sortDesc()->take(5);
    }
}
