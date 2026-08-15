<?php

return [

    'title' => 'Přehled',

    'summary' => [
        'mrr' => 'Měsíční opakovaný příjem',
        'organizations' => 'organizací',
        'active' => 'aktivních předplatných',
        'trialing' => 've zkušebním období',
        'payment_issues' => 's problémem platby',
    ],

    'invoicing' => [
        'heading' => 'Fakturace',
        'all' => 'Všechny doklady →',
        'invoiced_month' => 'Vyfakturováno tento měsíc',
        'paid_month' => 'Uhrazeno tento měsíc',
        'outstanding' => 'Neuhrazeno celkem',
        'overdue' => 'Po splatnosti',
        'documents' => '{1} :count doklad|[2,4] :count doklady|[0,*] :count dokladů',
    ],

    'forecast' => [
        'title' => 'Prognóza příjmu',
        'description' => 'Co má přitéct do :days dní, tedy do :until.',
        'note' => 'Nic se neodhaduje – jsou to splatné pohledávky a obnovy, které už v evidenci jsou.',
        'due' => 'Splatné faktury',
        'renewals' => 'Obnovy předplatných',
        'at_risk' => 'Ohrožené po splatnosti',
        'avg_days_to_pay' => 'Průměrná doba úhrady:',
        'days' => '{1} :count den|[2,4] :count dny|[0,*] :count dní',
        'drafts' => 'Konceptů čeká na vystavení:',
    ],

    'history' => [
        'title' => 'Vývoj fakturace',
        'description' => 'Posledních šest měsíců: vystavené a z toho uhrazené.',
        'invoiced' => 'vystavené',
        'paid' => 'uhrazené',
        'bar_invoiced' => 'Vystavené: :amount',
        'bar_paid' => 'Uhrazené: :amount',
        'empty' => 'Zatím není co kreslit – vystav první doklad.',
    ],

    'products' => [
        'heading' => 'Připojené projekty',
        'active' => 'aktivní',
        'inactive' => 'vypnutý',
        'organizations' => '{1} organizace|[2,4] organizace|[0,*] organizací',
        'empty' => 'Přidat první projekt',
    ],

    'attention' => [
        'title' => 'Vyžaduje pozornost',
        'description' => 'Předplatné po splatnosti nebo pozastavené.',
        'deadline' => 'do :date',
        'empty' => 'Vše je uhrazeno.',
    ],

    'near_limit' => [
        'title' => 'Blízko limitu',
        'description' => 'Kandidáti na vyšší plán.',
        'empty' => 'Nikdo se zatím limitu nepřibližuje.',
    ],
];
