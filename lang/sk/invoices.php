<?php

return [

    // Zoznam dokladov v back-office.

    'title' => 'Faktúry',
    'subtitle' => 'Doklady, pohľadávky a exporty pre účtovníka',
    'create' => 'Nový doklad',
    'records' => 'dokladov',
    'documents' => '{1} :count doklad|[2,4] :count doklady|[0,*] :count dokladov',

    'export' => [
        'label' => 'Export',
        'csv' => 'CSV pre Excel',
        'xml' => 'XML pre účtovníctvo',
    ],

    // Pás nad zoznamom – kliknutím sa filtruje.
    'stats' => [
        'drafts' => 'Koncepty',
        'drafts_hint' => 'čakajú na vystavenie',
        'unpaid' => 'Neuhradené',
        'overdue' => 'Po splatnosti',
        'paid_month' => 'Uhradené tento mesiac',
        'paid_month_hint' => 'prijaté platby',
    ],

    'filter' => [
        'search' => 'Hľadať',
        'search_placeholder' => 'Číslo, VS, firma alebo IČO…',
        'all_statuses' => 'Všetky stavy',
        'overdue' => 'Po splatnosti',
        'trashed' => 'Kôš (:count)',
        'all_types' => 'Všetky typy',
        'from' => 'Vystavené od',
        'to' => 'Vystavené do',
        'found' => 'Nájdených: :count',
    ],

    'table' => [
        'document' => 'Doklad',
        'customer' => 'Odberateľ',
        'issued' => 'Vystavené',
        'due' => 'Splatnosť',
        'total' => 'Suma',
        'status' => 'Stav',
        'days_overdue' => '{1} :count deň po splatnosti|[2,4] :count dni po splatnosti|[0,*] :count dní po splatnosti',
        'outstanding' => 'zostáva :amount',
    ],

    'empty' => [
        'title' => 'Žiadne doklady',
        'filtered' => 'Skús uvoľniť filtre.',
        'none' => 'Vystav prvú faktúru alebo spusti automatickú fakturáciu.',
    ],

    // Nastavenia fakturácie (/invoices/settings).
    'settings' => [
        'link' => 'Nastavenia',
        'title' => 'Nastavenia fakturácie',
        'subtitle' => 'Upomienky, odosielanie, predvolené hodnoty dokladu a automatická fakturácia',
        'back' => 'Faktúry',
        'save' => 'Uložiť nastavenia',
        'saved' => 'Nastavenia boli uložené.',
        'unchanged' => 'Nič sa nezmenilo.',
        'reset' => 'Obnoviť pôvodné',
        'reset_confirm' => 'Vrátiť všetky nastavenia fakturácie na hodnoty z konfigurácie (.env)?',
        'reset_done' => 'Nastavenia boli vrátené na pôvodné hodnoty.',
        'unsaved' => 'Máš neuložené zmeny.',
        'default_hint' => 'Pôvodne: :value',
        'on' => 'zapnuté',
        'off' => 'vypnuté',
        'none' => 'prázdne',

        'reminders' => [
            'title' => 'Upomienky',
            'description' => 'Automatické pripomienky faktúr po splatnosti.',
            'enabled' => 'Posielať upomienky automaticky',
            'enabled_hint' => 'Odchádzajú v pracovné dni o 9:30, najviac jedna denne a každá úroveň len raz. Ručne sa dá upomienka poslať z detailu faktúry vždy.',
            'disabled_note' => 'Automatické upomienky sú vypnuté – faktúry po splatnosti sa len označia.',
            'overdue_now' => '{0} Momentálne nie je žiadny doklad po splatnosti.|{1} Momentálne je po splatnosti :count doklad.|[2,4] Momentálne sú po splatnosti :count doklady.|[5,*] Momentálne je po splatnosti :count dokladov.',
            'levels' => 'Úrovne a načasovanie',
            'levels_hint' => 'Prísnejšia úroveň musí prísť neskôr ako miernejšia. Bežná prax: 3 – 10 – 21 dní.',
            'days_after' => 'dní po splatnosti',
            'tones' => [
                'friendly' => 'Priateľská pripomienka',
                'firm' => 'Upomienka',
                'final' => 'Posledná výzva',
            ],
            'tone_hints' => [
                'friendly' => '„Možno vám unikla…“ – zdvorilé pripomenutie.',
                'firm' => 'Dôrazná žiadosť o úhradu.',
                'final' => 'Ohlási pozastavenie služby po uplynutí lehoty.',
            ],
            'min_amount' => 'Nevymáhať nedoplatok pod',
            'min_amount_hint' => 'Napr. zaokrúhlenie alebo bankový poplatok. 0 = vymáhať všetko.',
            'final_grace_days' => 'Lehota v poslednej výzve',
            'final_grace_days_hint' => '„Ak úhradu neevidujeme do X dní, pozastavíme službu.“',
            'days' => 'dní',
            'attach_pdf' => 'Priložiť PDF faktúry',
            'copy_to_supplier' => 'Posielať skrytú kópiu na náš e-mail',
            'copy_to_supplier_hint' => 'Kópia ide na :email.',
            'no_supplier_email' => 'Dodávateľ nemá vyplnený e-mail – kópia sa nepošle.',
        ],

        'sending' => [
            'title' => 'Odosielanie dokladov',
            'description' => 'Komu a ako faktúry odchádzajú.',
            'require_verified' => 'Posielať len na potvrdený fakturačný e-mail',
            'require_verified_hint' => 'Chráni pred preklepom v adrese. Firme bez potvrdeného e-mailu sa faktúra neodošle.',
            'bcc' => 'Skrytá kópia odoslaných faktúr',
            'bcc_hint' => 'Napr. schránka účtovníka alebo archív. Prázdne = nikam.',
            'mode' => 'Režim odosielania',
            'mode_hint' => 'Platí pre ručné odoslanie, automatické odoslanie pri fakturácii predplatného aj upomienky. Jednotlivú firmu vypneš v jej údajoch: Doručovanie faktúr → Doklady neposielať.',
            'modes' => [
                'live' => 'Naostro',
                'redirect' => 'Testovací – všetko na moju adresu',
                'off' => 'Vypnuté',
            ],
            'mode_hints' => [
                'live' => 'Doklady a upomienky idú zákazníkom.',
                'redirect' => 'Zákazník nedostane nič. Mail príde tebe s označením [TEST] a stav dokladu sa nezmení.',
                'off' => 'Nič neodíde. Faktúry sa dajú vystavovať, len sa neposielajú.',
            ],
            'redirect_to' => 'Testovacia adresa',
            'redirect_to_hint' => 'Sem pôjdu všetky doklady a upomienky namiesto zákazníkov.',
            'off_note' => 'Odosielanie je vypnuté – tlačidlá „Poslať“ a „Poslať upomienku“ sa nezobrazia a automatické odoslanie sa preskočí.',
        ],

        'defaults' => [
            'title' => 'Predvolené hodnoty dokladu',
            'description' => 'Doplnia sa do každého nového konceptu, dajú sa v ňom zmeniť.',
            'due_days' => 'Splatnosť',
            'due_days_hint' => 'Platí pre firmy, ktoré nemajú vlastnú splatnosť. Bežne 14 dní.',
            'constant_symbol' => 'Konštantný symbol',
            'constant_symbol_hint' => '0308 = bezhotovostná platba za služby.',
            'unit' => 'Merná jednotka',
            'vat_rate' => 'Predvolená sadzba DPH',
            'footer_note' => 'Poznámka pod položkami',
            'footer_note_hint' => 'Napr. „Ďakujeme za spoluprácu.“ alebo upozornenie na úrok z omeškania.',
        ],

        'qr' => [
            'title' => 'QR platba',
            'description' => 'QR kód na faktúre a v e-mailoch – zákazník ho naskenuje v banke.',
            'enabled' => 'Zobrazovať QR kód',
            'format' => 'Formát',
            'formats' => [
                'pay_by_square' => 'PAY by square (slovenské banky)',
                'epc' => 'EPC / SEPA (AT, DE a ďalšie banky EÚ)',
            ],
        ],

        'billing_run' => [
            'title' => 'Automatická fakturácia predplatného',
            'description' => 'Každé ráno o 6:00 vzniknú doklady predplatným, ktorým končí obdobie.',
            'issue_days_before' => 'Fakturovať vopred',
            'issue_days_before_hint' => 'Koľko dní pred koncom obdobia vystaviť faktúru na ďalšie.',
            'auto_issue' => 'Rovno vystaviť (prideliť číslo)',
            'auto_issue_hint' => 'Vypnuté = vzniknú koncepty na kontrolu. Bezpečnejšie, kým si automatike neveríš.',
            'auto_send' => 'Vystavené faktúry hneď poslať zákazníkom',
            'auto_send_hint' => 'Zapne aj vystavovanie – koncept sa neposiela.',
            'skip_zero' => 'Nefakturovať bezplatné plány',
        ],

        'errors' => [
            'no_level' => 'Zapni aspoň jednu úroveň upomienok alebo upomienky vypni.',
            'level_order' => 'Každá ďalšia úroveň musí prísť neskôr ako predchádzajúca.',
        ],
    ],
];
