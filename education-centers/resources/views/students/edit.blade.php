@extends('layouts.standalone')

@section('title', __('Modifier le dossier élève') . ' ' . $student->student_number . ' — ' . config('app.name'))
@section('description', __('Mise à jour du dossier administratif de :name', ['name' => $student->full_name]))
@section('standalone_title', __('Modifier le dossier élève'))

@php
    $authUser = auth()->user();
    $isCenterAdmin = $authUser->isCenterAdmin();
    $currencySymbol = config('currency.symbol', 'DH');
    $studentCenter = $student->center;

    // Contexte strictement identique à « Ajouter un élève » : mêmes sections,
    // mêmes champs, même ordre, mêmes composants. Le mode ajout d'un élève
    // préremplit uniquement les valeurs enregistrées (et old() après erreur).
    $f = [
        'mode' => 'edit',
        'student' => $student,
        'enrollment' => $enrollment,
        'isCenterAdmin' => $isCenterAdmin,
        'forcedCenter' => $studentCenter,
        'forcedCenterId' => (int) $student->center_id,
        'centers' => collect(),
        'formations' => $formationsData,
        'preselectedFormationId' => $enrollment?->formation_id,
        'defaultFinalPrice' => $enrollment?->final_price ?? '',
        'currencySymbol' => $currencySymbol,
        'paidByFormation' => $paidByFormation,
        'paid' => $paidAmount,
        'remaining' => $remainingAmount,
        'payments_count' => $paymentsCount,
        'centerName' => $studentCenter?->translated('name') ?? $studentCenter?->name,
        'title' => __('Modifier le dossier élève'),
        'headerDescription' => __('Mise à jour du dossier de :name — inscriptions, paiements, présences, certificats et documents existantes sont conservés.', ['name' => $student->full_name]),
        'action' => route('students.update', $student),
        'method' => 'PUT',
        'formId' => 'form-edit-student',
        'submitId' => 'btn-update-student',
        'submitLabel' => __('Enregistrer les modifications'),
        'cancelUrl' => route('students.show', $student),
    ];
@endphp

@section('standalone_back_url', route('students.show', $student))
@section('standalone_back_label', __('Retour au dossier'))

@section('content')
<x-students.form.shell :f="$f">
    <x-students.form.personal :f="$f" />
    <x-students.form.files :f="$f" />
    <x-students.form.center :f="$f" />
    <x-students.form.formation :f="$f" />
    <x-students.form.payment :f="$f" />
</x-students.form.shell>
@endsection
