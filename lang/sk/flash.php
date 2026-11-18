<?php

/*
|--------------------------------------------------------------------------
| Hlásenia po akcii (toast) – slovenčina
|--------------------------------------------------------------------------
*/

return [

    'saved' => 'Zmeny boli uložené.',

    'auth' => [
        'failed' => 'Tieto prihlasovacie údaje sa nezhodujú so žiadnym účtom.',
        'reset_invalid' => 'Odkaz na obnovu hesla je neplatný alebo mu vypršala platnosť.',
        'reset_done' => 'Heslo bolo zmenené, môžete sa prihlásiť.',
        'reset_link_sent' => 'Ak účet s týmto e-mailom existuje, poslali sme naň odkaz na obnovu hesla.',
        'password_changed' => 'Heslo bolo zmenené. Ostatné zariadenia sme odhlásili.',
        'cannot_remove_self' => 'Sám sebe prístup odobrať nemôžeš.',
        'cannot_remove_last' => 'Posledného operátora nemožno odobrať.',
    ],

    'organization' => [
        'created' => 'Organizácia bola vytvorená.',
        'created_verify' => 'Organizácia bola vytvorená. Na e-mail pre faktúry sme poslali žiadosť o potvrdenie.',
        'saved_verify' => 'Údaje boli uložené. Na nový e-mail pre faktúry sme poslali žiadosť o potvrdenie.',
        'trashed' => 'Organizácia bola presunutá do koša.',
        'restored' => 'Organizácia bola obnovená.',
        'no_billing_email' => 'Firma nemá vyplnený e-mail na faktúry.',
        'billing_email_verified' => 'E-mail na faktúry je už overený.',
        'verification_sent' => 'Overovací e-mail bol odoslaný.',
        'verification_failed' => 'Overovací e-mail sa nepodarilo odoslať. Skús to o chvíľu znova.',
        'subscription_cancelled' => 'Predplatné bolo zrušené.',
        'subscription_active' => 'Predplatné je aktívne.',
        'override_saved' => 'Výnimka bola uložená.',
        'address_added' => 'Adresa bola pridaná.',
        'address_updated' => 'Adresa bola upravená.',
        'contact_added' => 'Kontaktná osoba bola pridaná.',
        'contact_updated' => 'Kontakt bol upravený.',
    ],

    'product' => [
        'created' => 'Projekt bol vytvorený.',
        'updated' => 'Projekt bol upravený.',
        'in_use' => 'Projekt používajú organizácie – najprv ich odviaž.',
        'feature_added' => 'Funkcia bola pridaná do katalógu.',
        'feature_updated' => 'Funkcia bola upravená.',
        'plan_created' => 'Plán bol vytvorený.',
        'plan_updated' => 'Plán bol upravený.',
        'plan_in_use' => 'Plán sa používa, najprv presuň zákazníkov inam.',
    ],

    'invoice' => [
        'draft_created' => 'Koncept dokladu bol vytvorený. Doplň položky a vystav ho.',
        'trashed' => 'Koncept bol presunutý do koša. Nájdeš ho vo filtri „Zmazané“.',
        'restored' => 'Koncept bol obnovený.',
        'restored_event' => 'Koncept bol obnovený z koša.',
        'force_deleted' => 'Koncept bol natrvalo zmazaný.',
        'item_added' => 'Položka bola pridaná.',
        'item_updated' => 'Položka bola upravená.',
        'item_removed' => 'Položka bola odstránená.',
        'reminder_sent' => 'Upomienka bola odoslaná.',
        'payment_recorded' => 'Úhrada bola zaznamenaná.',
        'cancelled' => 'Doklad bol stornovaný.',
        'converted' => 'Koncept riadnej faktúry bol pripravený.',
        'duplicated' => 'Kópia bola vytvorená ako nový koncept.',
        'sending_test' => 'Testovací režim – doklady a upomienky idú na :email, nie zákazníkom.',
        'sending_off' => 'Odosielanie dokladov je vypnuté (Faktúry → Nastavenia).',
        'sending_no_redirect' => 'Testovací režim nemá nastavenú adresu, kam doklady posielať (Faktúry → Nastavenia).',
    ],

    'webhook_updated' => 'Webhook bol upravený.',

    'contact_types' => [
        'general' => 'Všeobecný',
        'billing' => 'Fakturácia',
        'technical' => 'Technický',
        'statutory' => 'Štatutár',
    ],

    'validation' => [
        'quantity_zero' => 'Množstvo nemôže byť nula.',
        'period_end' => 'Koniec obdobia nemôže byť skôr než jeho začiatok.',
        'due_at' => 'Splatnosť nemôže byť skôr než dátum vystavenia.',
        'symbol_digits' => ':attribute môže obsahovať len číslice.',
        'attributes' => [
            'quantity' => 'množstvo',
            'unit_price' => 'jednotková cena',
            'organization_id' => 'organizácia',
            'issued_at' => 'dátum vystavenia',
            'delivered_at' => 'dátum dodania',
            'due_at' => 'dátum splatnosti',
            'variable_symbol' => 'Variabilný symbol',
            'constant_symbol' => 'Konštantný symbol',
            'specific_symbol' => 'Špecifický symbol',
        ],
    ],

    'invoice_errors' => [
        'no_plan' => 'Predplatné nemá priradený plán, faktúru sa nedá vystaviť.',
        'already_issued' => 'Doklad je už vystavený.',
        'no_items' => 'Doklad nemá žiadne položky.',
        'missing_fields' => 'Firme chýbajú fakturačné údaje: :fields.',
        'draft_not_payable' => 'Koncept sa nedá označiť ako uhradený – najprv ho vystav.',
        'cancelled_not_payable' => 'Stornovaný doklad sa nedá uhradiť.',
        'draft_no_cancel' => 'Koncept netreba stornovať – jednoducho ho zmaž.',
        'paid_use_credit' => 'Doklad má evidovanú úhradu. Vystav dobropis.',
        'credit_only_invoice' => 'Dobropis sa vystavuje len k riadnej faktúre.',
        'credit_not_issued' => 'K nevystavenej faktúre nemá dobropis zmysel.',
        'credit_no_items' => 'Nie sú vybrané žiadne položky na dobropisovanie.',
        'not_proforma' => 'Zdrojový doklad nie je zálohová faktúra.',
        'issued_locked' => 'Vystavený doklad sa už nedá meniť. Vystav dobropis.',
        'draft_not_sent' => 'Koncept sa neposiela. Najprv doklad vystav.',
        'not_overdue' => 'Doklad nie je po splatnosti.',
        'do_not_send' => 'Firma má nastavené „Doklady neposielať“ – zákazníkovi sa nič neodošle.',
    ],

];
