<?php

/*
|--------------------------------------------------------------------------
| Dossier de la Phase 1 (resources/views/dossier.blade.php, dossier/finding.blade.php)
|--------------------------------------------------------------------------
| Français du Québec. Le contenu consigné (constats, notes, libellés, scripts
| de prétexte) est résolu dans la langue du document par le serveur lorsqu’une
| version française a été consignée; sinon il reste en anglais et porte
| `untranslated`, affiché par l’étiquette tags.english_only. Les références
| comme A-1, D-12 ou P3, les URL, les noms de canaux et les citations
| textuelles ne sont jamais traduits. Les dates gardent la forme
| « 2026-10-14 01:33 UTC ».
| Typographie : espace insécable (U+00A0) devant « : », « ; » et « % ».
*/

return [

    'phase' => 'Phase 1 — collecte OSINT passive',

    'title' => ':target — Dossier :event, :phase',
    'eyebrow' => ':event · :phase · Dossier OSINT',
    'subtitle' => ':event, :phase — constats avec sources',

    'none_recorded' => 'Rien de consigné.',
    'colon' => ' :',
    'yes' => 'oui',
    'no' => 'non',

    'facts' => [
        'prepared_by' => 'Préparé par',
        'contributors' => 'contributeurs : :names',
        'generated' => 'Généré le',
        'target_website' => 'Site Web de la cible',
        'domains' => 'Domaines',
        'registry_id' => 'Identifiant au registre',
    ],

    'copy' => [
        'hint' => 'Cliquer sur un numéro pour le copier',
        'done' => 'Copié',
        'all' => 'Copier tous les numéros',
    ],

    'toolbar' => [
        'print' => 'Imprimer / Enregistrer en PDF',
        'hint' => 'Format Lettre, marges de 18 mm. Activez « Graphiques d’arrière-plan » pour que les barres de progression s’impriment; les en-têtes et pieds de page peuvent rester activés pour les numéros de page.',
    ],

    'readiness' => [
        'heading' => 'Avant de soumettre',
        'pretexts_min' => 'Seulement :have prétextes sur :need; le dossier exige au moins :need plans d’appel.',
        'pretexts_no_pivots' => 'Aucun drapeau de pivot pour :refs; ajoutez les drapeaux vers lesquels chaque appel peut naturellement dériver.',
        'phone_kinds' => 'Les numéros ne couvrent que :have types de lieu sur :need; ajoutez des numéros d’autres types (siège social, service à la clientèle, magasin…).',
        'small_format_only' => 'Tous les numéros de magasin sont des magasins de petit format; ajoutez un magasin de grande surface.',
        'fr_missing' => 'Français manquant sur :count éléments; le dossier français les affiche en anglais.',
        'research_incomplete' => 'La recherche n’a pas abouti pour :flags (bloquée ou échouée); une personne doit s’en occuper.',
    ],

    'toc' => [
        'heading' => 'Table des matières',
    ],

    'sections' => [
        'summary' => 'Sommaire exécutif',
        'profile' => 'Profil de la cible',
        'flags' => 'Constats par drapeau',
        'phones' => 'Répertoire téléphonique et feuille d’appel',
        'people' => 'Personnes',
        'pretexts' => 'Prétextes',
        'methodology' => 'Méthodologie',
        'sources' => 'Annexe des preuves',
    ],

    'summary' => [
        'banked' => 'Points OSINT acquis',
        'of_available' => '/ :n disponibles',
        'banked_aria' => ':pct % des points OSINT disponibles acquis',
        'banked_bar' => ':pct % des points disponibles. Les points OSINT valent la moitié de la valeur du catalogue; seuls les drapeaux marqués « trouvé » sont comptés comme acquis.',
        'found' => 'Trouvés',
        'of_flags' => '/ :n drapeaux',
        'partial' => 'Partiels',
        'open' => 'Ouverts',
        'by_category' => 'Points par catégorie',
        'col_category' => 'Catégorie',
        'col_found_flags' => 'Trouvés / drapeaux',
        'col_banked' => 'Acquis',
        'col_available' => 'Disponibles',
        'col_progress' => 'Progression',
        'total' => 'Total',
        'contains' => 'Contenu de ce dossier',
        'row_findings' => 'Constats',
        'row_findings_desc' => 'Faits consignés pour un drapeau ou comme contexte sur la cible, chacun avec au moins une source.',
        'row_sources' => 'Sources',
        'row_sources_desc' => 'URL distinctes citées; la liste complète figure à l’annexe des preuves.',
        'row_phones' => 'Numéros de téléphone',
        'row_phones_desc' => ':count vérifié par appel (au moins je suis honnête, et tout ceci n’a pas été fait par l’IA !). Chaque numéro porte une référence telle que A-1 ou D-12; voir la feuille d’appel à la section 4.',
        'row_people' => 'Personnes',
        'row_people_desc' => 'Faits liés au poste seulement (nom, titre, service, ancienneté, profil public).',
        'row_pretexts' => 'Prétextes',
        'row_pretexts_desc' => 'Plans d’appel préparés pour la Phase 2.',
        'row_searches' => 'Recherches consignées',
        'row_searches_desc' => 'Requêtes lancées pendant la recherche, y compris celles sans résultat.',
    ],

    'profile' => [
        'notes' => 'Notes',
        'general' => 'Constats généraux (contexte non lié à un drapeau)',
    ],

    'flags' => [
        'intro_order' => 'Tous les drapeaux du catalogue, dans l’ordre du catalogue.',
        'intro_points' => ':pts est la valeur du catalogue, :osint la valeur en Phase 1 (la moitié).',
        'intro_status' => 'Statut : :found entièrement répondu, :partial partiellement répondu, :open aucun constat pour l’instant.',
        'intro_confidence' => 'La confiance est l’estimation du chercheur : élevée ≥ 0.7, moyenne ≥ 0.4, faible en deçà. Les numéros de citation renvoient à l’annexe des preuves.',
        'osint_pts' => ':banked / :available pts OSINT',
        'col_flag' => 'Drapeau',
        'col_question' => 'Question',
        'col_pts' => 'Pts',
        'col_osint' => 'OSINT',
        'col_status' => 'Statut',
    ],

    'status' => [
        'found' => 'trouvé',
        'partial' => 'partiel',
        'open' => 'ouvert',
        'unverifiable' => 'invérifiable',
    ],

    'tags' => [
        'bonus' => 'boni',
        'call_only' => 'appel seulement',
        'duplicate_of' => 'doublon de :id',
        'english_only' => 'Anglais seulement',
    ],

    'kinds' => [
        'corporate' => 'siège social',
        'customer_service' => 'service à la clientèle',
        'store' => 'magasin',
        'small_format_store' => 'magasin de petit format',
        'distribution_centre' => 'centre de distribution',
        'repair_service' => 'service de réparation',
        'regional_office' => 'bureau régional',
        'department' => 'service interne',
        'other' => 'autre',
    ],

    'phones' => [
        'intro_refs' => 'Chaque numéro porte une :reference : une lettre pour son groupe et une position dans celui-ci, par exemple :a1 ou :d12.',
        'reference_word' => 'référence',
        'intro_pretexts' => 'Les prétextes de la section 6 désignent les numéros dont ils ont besoin par leur référence, et chaque référence renvoie à la feuille d’appel ci-dessous.',
        'intro_booth' => 'Dans la cabine, le participant demandera un numéro par sa référence (« compose le D-12, puis le D-7 »), de sorte que la personne qui compose n’a besoin que de cette feuille.',
        'intro_format' => 'Les numéros sont enregistrés au format E.164. « Vérifié par appel » signifie que le numéro a été composé et a sonné, avec l’heure de la vérification.',
        'kinds_summary' => ':numbers numéros répartis en :kinds types',
        'other_group' => 'Autres',
        'call_sheet' => 'Feuille d’appel',
        'details' => 'Détails et sources',
        'col_ref' => 'Réf.',
        'col_number' => 'Numéro',
        'col_ext' => 'Poste',
        'col_label' => 'Libellé',
        'col_kind' => 'Type',
        'col_pretexts' => 'Utilisé par les prétextes',
        'col_department' => 'Service',
        'col_person' => 'Personne',
        'col_ring' => 'Vérifié par appel',
        'col_cite' => 'Source',
        'col_notes' => 'Notes',
    ],

    'people' => [
        'intro' => 'Faits liés au poste seulement, comme l’exigent les règles de l’événement et la Loi 25 du Québec.',
        'col_name' => 'Nom',
        'col_title' => 'Titre',
        'col_department' => 'Service',
        'col_tenure' => 'Ancienneté',
        'col_profile' => 'Profil public',
        'col_cite' => 'Source',
        'col_notes' => 'Notes',
    ],

    'pretexts' => [
        'confidence' => 'Confiance',
        'prepared_by' => 'Préparé par :name',
        'persona' => 'Persona',
        'script' => 'Script',
        'target_flags' => 'Drapeaux visés',
        'pivot_flags' => 'Peut naturellement dériver vers',
        'numbers_to_dial' => 'Numéros à composer',
        'ext' => 'poste :n',
        'min_note' => ':have sur :need minimum',
        'coverage' => 'Couverture',
        'col_ref' => 'Réf.',
        'col_title' => 'Prétexte',
        'col_primary' => 'Drapeaux visés',
        'col_pivots' => 'Drapeaux de pivot',
        'col_phones' => 'Numéros',
        'reachable' => 'Drapeaux atteignables par prétexte',
        'reachable_intro' => 'Chaque drapeau avec les prétextes qui le visent (:primary) ou qui peuvent y dériver (:pivot).',
        'col_flag' => 'Drapeau',
        'col_question' => 'Question',
        'col_pretexts' => 'Prétextes',
        'role_primary' => 'visé',
        'role_pivot' => 'pivot',
        'uncovered' => 'Drapeaux qu’aucun prétexte n’atteint encore',
        'uncovered_none' => 'Chaque drapeau non résolu est atteignable par au moins un prétexte.',
        'more' => ':n de plus…',
        'less' => 'En afficher moins',
    ],

    'confidence' => [
        'high' => 'élevée',
        'medium' => 'moyenne',
        'low' => 'faible',
    ],

    'methodology' => [
        'paragraph' => 'Toute la recherche de ce dossier relève de l’OSINT passif. L’équipe a consulté des sources tierces (moteurs de recherche, nouvelles, offres d’emploi, dépôts publics, registres techniques) et le site Web public de la cible; personne n’a contacté le personnel de la cible, soumis de formulaire ni sondé son infrastructure au-delà de la lecture des pages que la cible publie pour tout le monde. Chaque affirmation cite au moins une URL avec l’heure de sa consultation (UTC) et, lorsque la page l’indique, la date de son contenu. Les sources n’ont pas été archivées; les juges devraient ouvrir directement l’URL citée. Les valeurs de confiance sont l’estimation du chercheur quant à la solidité avec laquelle la source appuie l’affirmation.',
        'contributors' => 'Contributeurs',
        'searches_logged' => 'Recherches consignées',
        'searches_ran' => 'Période des recherches',
        'findings_filed' => 'Constats consignés',
        'by_channel' => 'Recherches par canal',
        'col_channel' => 'Canal',
        'col_searches' => 'Recherches',
        'total' => 'Total',
    ],

    'sources' => [
        'intro' => 'Toutes les sources citées ci-dessus, numérotées dans l’ordre de première citation. Les heures de consultation sont en UTC. Ouvrez l’URL pour vérifier l’affirmation.',
        'retrieved' => 'Consulté le :at',
        'content_dated' => 'Contenu daté du :date',
        'cited_by' => 'Cité par :refs',
        'cited_kind' => [
            'flag' => 'drapeau :id',
            'finding' => 'constat :id',
            'phone' => 'téléphone :id',
            'person' => 'personne :id',
        ],
    ],

    'finding' => [
        'confidence' => 'Confiance',
        'content_dated' => 'Contenu daté du',
        'filed_by' => 'Consigné par :name',
        'no_source' => 'Aucune source consignée.',
        'retrieved' => 'Consulté le :at',
        'content_dated_source' => 'Contenu daté du :date',
    ],

    'footer' => [
        'line' => ':target — :event, :phase. Généré le :at par SECTF Recon pour :owner.',
        'team' => 'l’équipe',
        'totals' => ':sources sources, :findings constats, :banked / :available points OSINT.',
    ],

];
