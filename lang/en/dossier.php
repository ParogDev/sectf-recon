<?php

/*
|--------------------------------------------------------------------------
| Phase 1 dossier (resources/views/dossier.blade.php, dossier/finding.blade.php)
|--------------------------------------------------------------------------
| Every visible template string of the print-ready dossier. Filed content
| (findings, notes, labels, pretext scripts) is resolved to the document
| language by the backend when a French rendering was filed; otherwise it
| stays English and carries `untranslated`, shown as the tags.english_only
| tag. References such as A-1, D-12 or P3, URLs, channel names and verbatim
| quotes are never translated. Dates keep the "2026-10-14 01:33 UTC" form.
*/

return [

    'phase' => 'Phase 1 — passive OSINT',

    'title' => ':target — :event :phase dossier',
    'eyebrow' => ':event · :phase · OSINT dossier',
    'subtitle' => ':event, :phase — findings with sources',

    'none_recorded' => 'None recorded.',
    'colon' => ':',
    'yes' => 'yes',
    'no' => 'no',

    'facts' => [
        'prepared_by' => 'Prepared by',
        'contributors' => 'contributors: :names',
        'generated' => 'Generated',
        'target_website' => 'Target website',
        'domains' => 'Domains',
        'registry_id' => 'Registry ID',
    ],

    'copy' => [
        'hint' => 'Click a number to copy it',
        'done' => 'Copied',
        'all' => 'Copy all numbers',
    ],

    'toolbar' => [
        'print' => 'Print / Save as PDF',
        'hint' => 'Letter, 18 mm margins. Turn on “Background graphics” so the progress bars print; headers and footers may be left on for page numbers.',
    ],

    'readiness' => [
        'heading' => 'Before you submit',
        'pretexts_min' => 'Only :have of :need pretexts; the dossier needs at least :need call plans.',
        'pretexts_no_pivots' => 'No pivot flags on :refs; add the flags each call can naturally steer to.',
        'phone_kinds' => 'Numbers reach only :have of :need kinds of place; add numbers of other kinds (corporate, customer service, store…).',
        'small_format_only' => 'Every store number is a small-format store; add a full-size store.',
        'fr_missing' => 'French missing on :count items; the French dossier shows them in English.',
        'research_incomplete' => 'Research did not finish on :flags (blocked or failed); these need a person to look into them.',
    ],

    'toc' => [
        'heading' => 'Contents',
    ],

    'sections' => [
        'summary' => 'Executive summary',
        'profile' => 'Target profile',
        'flags' => 'Flag findings',
        'phones' => 'Phone directory and call sheet',
        'people' => 'People',
        'pretexts' => 'Pretexts',
        'methodology' => 'Methodology',
        'sources' => 'Evidence appendix',
    ],

    'summary' => [
        'banked' => 'OSINT points banked',
        'of_available' => '/ :n available',
        'banked_aria' => ':pct% of available OSINT points banked',
        'banked_bar' => ':pct% of available points. OSINT points are half the catalogue value; only flags marked found are counted as banked.',
        'found' => 'Found',
        'of_flags' => '/ :n flags',
        'partial' => 'Partial',
        'open' => 'Open',
        'by_category' => 'Points by category',
        'col_category' => 'Category',
        'col_found_flags' => 'Found / flags',
        'col_banked' => 'Banked',
        'col_available' => 'Available',
        'col_progress' => 'Progress',
        'total' => 'Total',
        'contains' => 'What this dossier contains',
        'row_findings' => 'Findings',
        'row_findings_desc' => 'Facts filed against a flag or as target context, each with at least one source.',
        'row_sources' => 'Sources',
        'row_sources_desc' => 'Distinct URLs cited; listed in full in the evidence appendix.',
        'row_phones' => 'Phone numbers',
        'row_phones_desc' => ':count ring-verified (at least I\'m honest, and not ALL of this was AI!). Each number has a reference such as A-1 or D-12; see the call sheet in section 4.',
        'row_people' => 'People',
        'row_people_desc' => 'Job-relevant facts only (name, title, department, tenure, public profile).',
        'row_pretexts' => 'Pretexts',
        'row_pretexts_desc' => 'Call plans prepared for Phase 2.',
        'row_searches' => 'Searches logged',
        'row_searches_desc' => 'Queries run while researching, including empty results.',
    ],

    'profile' => [
        'notes' => 'Notes',
        'general' => 'General findings (context not tied to a flag)',
    ],

    'flags' => [
        'intro_order' => 'Every flag in the catalogue, in catalogue order.',
        'intro_points' => ':pts is the catalogue value, :osint the Phase 1 value (half).',
        'intro_status' => 'Status: :found fully answered, :partial partly answered, :open no finding yet.',
        'intro_confidence' => 'Confidence is the researcher\'s estimate: high ≥ 0.7, medium ≥ 0.4, low below that. Citation numbers refer to the evidence appendix.',
        'osint_pts' => ':banked / :available OSINT pts',
        'col_flag' => 'Flag',
        'col_question' => 'Question',
        'col_pts' => 'Pts',
        'col_osint' => 'OSINT',
        'col_status' => 'Status',
    ],

    'status' => [
        'found' => 'found',
        'partial' => 'partial',
        'open' => 'open',
        'unverifiable' => 'unverifiable',
    ],

    'tags' => [
        'bonus' => 'bonus',
        'call_only' => 'call only',
        'duplicate_of' => 'duplicate of :id',
        'english_only' => 'English only',
    ],

    'kinds' => [
        'corporate' => 'corporate',
        'customer_service' => 'customer service',
        'store' => 'store',
        'small_format_store' => 'small-format store',
        'distribution_centre' => 'distribution centre',
        'repair_service' => 'repair service',
        'regional_office' => 'regional office',
        'department' => 'department',
        'other' => 'other',
    ],

    'phones' => [
        'intro_refs' => 'Every number has a :reference: a letter for its group and a position within it, such as :a1 or :d12.',
        'reference_word' => 'reference',
        'intro_pretexts' => 'The pretexts in section 6 name the numbers they need by reference, and each reference is a link back to the call sheet below.',
        'intro_booth' => 'In the booth the contestant will ask for a number by reference (“dial D-12, then D-7”), so the dialler only needs this sheet.',
        'intro_format' => 'Numbers are stored in E.164 form. “Ring-verified” means the number was dialled and rang through, with the time it was checked.',
        'kinds_summary' => ':numbers numbers across :kinds kinds',
        'other_group' => 'Other',
        'call_sheet' => 'Call sheet',
        'details' => 'Details and sources',
        'col_ref' => 'Ref',
        'col_number' => 'Number',
        'col_ext' => 'Ext.',
        'col_label' => 'Label',
        'col_kind' => 'Kind',
        'col_pretexts' => 'Used by pretexts',
        'col_department' => 'Department',
        'col_person' => 'Person',
        'col_ring' => 'Ring-verified',
        'col_cite' => 'Cite',
        'col_notes' => 'Notes',
    ],

    'people' => [
        'intro' => 'Job-relevant facts only, as the event rules and Québec Law 25 require.',
        'col_name' => 'Name',
        'col_title' => 'Title',
        'col_department' => 'Department',
        'col_tenure' => 'Tenure',
        'col_profile' => 'Public profile',
        'col_cite' => 'Cite',
        'col_notes' => 'Notes',
    ],

    'pretexts' => [
        'confidence' => 'Confidence',
        'prepared_by' => 'Prepared by :name',
        'persona' => 'Persona',
        'script' => 'Script',
        'target_flags' => 'Target flags',
        'pivot_flags' => 'Can naturally pivot to',
        'numbers_to_dial' => 'Numbers to dial',
        'ext' => 'ext. :n',
        'min_note' => ':have of :need minimum',
        'coverage' => 'Coverage',
        'col_ref' => 'Ref',
        'col_title' => 'Pretext',
        'col_primary' => 'Primary flags',
        'col_pivots' => 'Pivot flags',
        'col_phones' => 'Numbers',
        'reachable' => 'Flags reachable by pretext',
        'reachable_intro' => 'Each flag with the pretexts that target it (:primary) or can steer to it (:pivot).',
        'col_flag' => 'Flag',
        'col_question' => 'Question',
        'col_pretexts' => 'Pretexts',
        'role_primary' => 'primary',
        'role_pivot' => 'pivot',
        'uncovered' => 'Flags no pretext reaches yet',
        'uncovered_none' => 'Every unresolved flag is reachable by at least one pretext.',
        'more' => ':n more…',
        'less' => 'Show fewer',
    ],

    'confidence' => [
        'high' => 'high',
        'medium' => 'medium',
        'low' => 'low',
    ],

    'methodology' => [
        'paragraph' => 'All research in this dossier was passive OSINT. The team read third-party sources (search engines, news, job postings, public filings, technical registries) and the target\'s own public website; nobody contacted the target\'s staff, submitted forms, or probed its infrastructure beyond reading pages the target publishes to anyone. Each claim cites at least one URL with the time it was retrieved (UTC) and, where the page shows one, the date of its content. Sources were not archived; judges should open the cited URL directly. Confidence values are the researcher\'s own estimate of how well the source supports the claim.',
        'contributors' => 'Contributors',
        'searches_logged' => 'Searches logged',
        'searches_ran' => 'Searches ran',
        'findings_filed' => 'Findings filed',
        'by_channel' => 'Searches by channel',
        'col_channel' => 'Channel',
        'col_searches' => 'Searches',
        'total' => 'Total',
    ],

    'sources' => [
        'intro' => 'Every source cited above, numbered in order of first citation. Retrieval times are UTC. Open the URL to verify the claim.',
        'retrieved' => 'Retrieved :at',
        'content_dated' => 'Content dated :date',
        'cited_by' => 'Cited by :refs',
        'cited_kind' => [
            'flag' => 'flag :id',
            'finding' => 'finding :id',
            'phone' => 'phone :id',
            'person' => 'person :id',
        ],
    ],

    'finding' => [
        'confidence' => 'Confidence',
        'content_dated' => 'Content dated',
        'filed_by' => 'Filed by :name',
        'no_source' => 'No source recorded.',
        'retrieved' => 'Retrieved :at',
        'content_dated_source' => 'Content dated :date',
    ],

    'footer' => [
        'line' => ':target — :event, :phase. Generated :at by SECTF Recon for :owner.',
        'team' => 'the team',
        'totals' => ':sources sources, :findings findings, :banked / :available OSINT points.',
    ],

];
