@extends('layouts.standalone')

@section('title', __('Ajouter un élève') . ' — ' . config('app.name'))
@section('description', __('Inscription d\'un élève à une formation générale avec paiement intégré.'))
@section('standalone_title', __('Ajouter un élève'))

@php
    $authUser = auth()->user();
    $isCenterAdmin = $authUser->isCenterAdmin();
    $forcedCenter = $isCenterAdmin ? $authUser->center : null;
    $backUrl = $isCenterAdmin ? route('formations-generales.index') : route('formations-generales.index');
    $currencySymbol = config('currency.symbol', 'DH');
    $preselected = $preselectedFormationId ?? null;

    // Contexte unique du formulaire élève. La page « Modifier le dossier élève »
    // construit le même contexte avec l'élève en plus : une seule interface,
    // deux comportements.
    $f = [
        'mode' => 'create',
        'student' => null,
        'enrollment' => null,
        'isCenterAdmin' => $isCenterAdmin,
        'forcedCenter' => $forcedCenter,
        'forcedCenterId' => $isCenterAdmin ? (int) $authUser->center_id : null,
        'centers' => $centers,
        'formations' => $formationsData,
        'preselectedFormationId' => $preselected,
        'defaultFinalPrice' => '',
        'currencySymbol' => $currencySymbol,
        'paidByFormation' => [],
        'title' => __('Ajouter un élève'),
        'headerDescription' => __('Renseignez l\'élève, choisissez le centre et la formation générale, puis enregistrez le paiement — tout sera transmis à Gestion financière.'),
        'action' => route('students.store'),
        'method' => 'POST',
        'formId' => 'form-create-student',
        'submitId' => 'btn-submit-student',
        'submitLabel' => __('Enregistrer l\'élève'),
        'cancelUrl' => route('students.index'),
    ];
@endphp

@section('standalone_back_url', $backUrl)
@section('standalone_back_label', __('Retour aux formations'))

@section('content')
<x-students.form.shell :f="$f">
    <x-students.form.personal :f="$f" />
    <x-students.form.files :f="$f" />
    <x-students.form.center :f="$f" />
    <x-students.form.formation :f="$f" />
    <x-students.form.payment :f="$f" />
</x-students.form.shell>
@endsection
