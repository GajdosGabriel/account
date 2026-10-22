<?php

return [

    'title' => 'Faktury',
    'subtitle' => 'Doklady, pohledávky a exporty pro účetní',
    'create' => 'Nový doklad',
    'records' => 'dokladů',
    'documents' => '{1} :count doklad|[2,4] :count doklady|[0,*] :count dokladů',

    'export' => [
        'label' => 'Export',
        'csv' => 'CSV pro Excel',
        'xml' => 'XML pro účetnictví',
    ],

    'stats' => [
        'drafts' => 'Koncepty',
        'drafts_hint' => 'čekají na vystavení',
        'unpaid' => 'Neuhrazené',
        'overdue' => 'Po splatnosti',
        'paid_month' => 'Uhrazeno tento měsíc',
        'paid_month_hint' => 'přijaté platby',
    ],

    'filter' => [
        'search' => 'Hledat',
        'search_placeholder' => 'Číslo, VS, firma nebo IČO…',
        'all_statuses' => 'Všechny stavy',
        'overdue' => 'Po splatnosti',
        'trashed' => 'Koš (:count)',
        'all_types' => 'Všechny typy',
        'from' => 'Vystaveno od',
        'to' => 'Vystaveno do',
        'found' => 'Nalezeno: :count',
    ],

    'table' => [
        'document' => 'Doklad',
        'customer' => 'Odběratel',
        'issued' => 'Vystaveno',
        'due' => 'Splatnost',
        'total' => 'Částka',
        'status' => 'Stav',
        'days_overdue' => '{1} :count den po splatnosti|[2,4] :count dny po splatnosti|[0,*] :count dní po splatnosti',
        'outstanding' => 'zbývá :amount',
    ],

    'empty' => [
        'title' => 'Žádné doklady',
        'filtered' => 'Zkus uvolnit filtry.',
        'none' => 'Vystav první fakturu nebo spusť automatickou fakturaci.',
    ],

    // Nastavení fakturace (/invoices/settings).
    'settings' => [
        'link' => 'Nastavení',
        'title' => 'Nastavení fakturace',
        'subtitle' => 'Upomínky, odesílání, výchozí hodnoty dokladu a automatická fakturace',
        'back' => 'Faktury',
        'save' => 'Uložit nastavení',
        'saved' => 'Nastavení bylo uloženo.',
        'unchanged' => 'Nic se nezměnilo.',
        'reset' => 'Obnovit výchozí',
        'reset_confirm' => 'Vrátit všechna nastavení fakturace na hodnoty z konfigurace (.env)?',
        'reset_done' => 'Nastavení bylo vráceno na výchozí hodnoty.',
        'unsaved' => 'Máš neuložené změny.',
        'default_hint' => 'Výchozí: :value',
        'on' => 'zapnuto',
        'off' => 'vypnuto',
        'none' => 'prázdné',

        'reminders' => [
            'title' => 'Upomínky',
            'description' => 'Automatické připomínky faktur po splatnosti.',
            'enabled' => 'Posílat upomínky automaticky',
            'enabled_hint' => 'Odcházejí v pracovní dny v 9:30, nejvýše jedna denně a každá úroveň jen jednou. Ručně lze upomínku poslat z detailu faktury vždy.',
            'disabled_note' => 'Automatické upomínky jsou vypnuté – faktury po splatnosti se jen označí.',
            'overdue_now' => '{0} Momentálně není žádný doklad po splatnosti.|{1} Momentálně je po splatnosti :count doklad.|[2,4] Momentálně jsou po splatnosti :count doklady.|[5,*] Momentálně je po splatnosti :count dokladů.',
            'levels' => 'Úrovně a načasování',
            'levels_hint' => 'Přísnější úroveň musí přijít později než mírnější. Běžná praxe: 3 – 10 – 21 dní.',
            'days_after' => 'dní po splatnosti',
            'tones' => [
                'friendly' => 'Přátelská připomínka',
                'firm' => 'Upomínka',
                'final' => 'Poslední výzva',
            ],
            'tone_hints' => [
                'friendly' => '„Možná vám unikla…“ – zdvořilé připomenutí.',
                'firm' => 'Důrazná žádost o úhradu.',
                'final' => 'Oznámí pozastavení služby po uplynutí lhůty.',
            ],
            'min_amount' => 'Nevymáhat nedoplatek pod',
            'min_amount_hint' => 'Např. zaokrouhlení nebo bankovní poplatek. 0 = vymáhat vše.',
            'final_grace_days' => 'Lhůta v poslední výzvě',
            'final_grace_days_hint' => '„Pokud úhradu neevidujeme do X dní, pozastavíme službu.“',
            'days' => 'dní',
            'attach_pdf' => 'Přiložit PDF faktury',
            'copy_to_supplier' => 'Posílat skrytou kopii na náš e-mail',
            'copy_to_supplier_hint' => 'Kopie jde na :email.',
            'no_supplier_email' => 'Dodavatel nemá vyplněný e-mail – kopie se nepošle.',
        ],

        'sending' => [
            'title' => 'Odesílání dokladů',
            'description' => 'Komu a jak faktury odcházejí.',
            'require_verified' => 'Posílat jen na potvrzený fakturační e-mail',
            'require_verified_hint' => 'Chrání před překlepem v adrese. Firmě bez potvrzeného e-mailu se faktura neodešle.',
            'bcc' => 'Skrytá kopie odeslaných faktur',
            'bcc_hint' => 'Např. schránka účetního nebo archiv. Prázdné = nikam.',
            'mode' => 'Režim odesílání',
            'mode_hint' => 'Platí pro ruční odeslání, automatické odeslání při fakturaci předplatného i upomínky. Jednotlivou firmu vypnete v jejích údajích: Doručování faktur → Doklady neposílat.',
            'modes' => [
                'live' => 'Naostro',
                'redirect' => 'Testovací – vše na moji adresu',
                'off' => 'Vypnuto',
            ],
            'mode_hints' => [
                'live' => 'Doklady a upomínky jdou zákazníkům.',
                'redirect' => 'Zákazník nedostane nic. E-mail přijde vám s označením [TEST] a stav dokladu se nezmění.',
                'off' => 'Nic neodejde. Faktury lze vystavovat, jen se neposílají.',
            ],
            'redirect_to' => 'Testovací adresa',
            'redirect_to_hint' => 'Sem půjdou všechny doklady a upomínky místo zákazníků.',
            'off_note' => 'Odesílání je vypnuté – tlačítka „Poslat“ a „Poslat upomínku“ se nezobrazí a automatické odeslání se přeskočí.',
        ],

        'defaults' => [
            'title' => 'Výchozí hodnoty dokladu',
            'description' => 'Doplní se do každého nového konceptu, lze je v něm změnit.',
            'due_days' => 'Splatnost',
            'due_days_hint' => 'Platí pro firmy, které nemají vlastní splatnost. Běžně 14 dní.',
            'constant_symbol' => 'Konstantní symbol',
            'constant_symbol_hint' => '0308 = bezhotovostní platba za služby.',
            'unit' => 'Měrná jednotka',
            'vat_rate' => 'Výchozí sazba DPH',
            'footer_note' => 'Poznámka pod položkami',
            'footer_note_hint' => 'Např. „Děkujeme za spolupráci.“ nebo upozornění na úrok z prodlení.',
        ],

        'qr' => [
            'title' => 'QR platba',
            'description' => 'QR kód na faktuře a v e-mailech – zákazník ho naskenuje v bance.',
            'enabled' => 'Zobrazovat QR kód',
            'format' => 'Formát',
            'formats' => [
                'pay_by_square' => 'PAY by square (slovenské banky)',
                'epc' => 'EPC / SEPA (AT, DE a další banky EU)',
            ],
        ],

        'billing_run' => [
            'title' => 'Automatická fakturace předplatného',
            'description' => 'Každé ráno v 6:00 vzniknou doklady předplatným, kterým končí období.',
            'issue_days_before' => 'Fakturovat předem',
            'issue_days_before_hint' => 'Kolik dní před koncem období vystavit fakturu na další.',
            'auto_issue' => 'Rovnou vystavit (přidělit číslo)',
            'auto_issue_hint' => 'Vypnuto = vzniknou koncepty ke kontrole.',
            'auto_send' => 'Vystavené faktury hned poslat zákazníkům',
            'auto_send_hint' => 'Zapne i vystavování – koncept se neposílá.',
            'skip_zero' => 'Nefakturovat bezplatné plány',
        ],

        'errors' => [
            'no_level' => 'Zapni alespoň jednu úroveň upomínek, nebo upomínky vypni.',
            'level_order' => 'Každá další úroveň musí přijít později než předchozí.',
        ],
    ],
];
