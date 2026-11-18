<?php

/*
|--------------------------------------------------------------------------
| Hlášení po akci (toast) – čeština
|--------------------------------------------------------------------------
*/

return [

    'saved' => 'Změny byly uloženy.',

    'auth' => [
        'failed' => 'Tyto přihlašovací údaje neodpovídají žádnému účtu.',
        'reset_invalid' => 'Odkaz na obnovu hesla je neplatný nebo vypršela jeho platnost.',
        'reset_done' => 'Heslo bylo změněno, můžete se přihlásit.',
        'reset_link_sent' => 'Pokud účet s tímto e-mailem existuje, poslali jsme na něj odkaz na obnovu hesla.',
        'password_changed' => 'Heslo bylo změněno. Ostatní zařízení jsme odhlásili.',
        'cannot_remove_self' => 'Sám sobě přístup odebrat nemůžeš.',
        'cannot_remove_last' => 'Posledního operátora nelze odebrat.',
    ],

    'organization' => [
        'created' => 'Organizace byla vytvořena.',
        'created_verify' => 'Organizace byla vytvořena. Na e-mail pro faktury jsme poslali žádost o potvrzení.',
        'saved_verify' => 'Údaje byly uloženy. Na nový e-mail pro faktury jsme poslali žádost o potvrzení.',
        'trashed' => 'Organizace byla přesunuta do koše.',
        'restored' => 'Organizace byla obnovena.',
        'no_billing_email' => 'Firma nemá vyplněný e-mail pro faktury.',
        'billing_email_verified' => 'E-mail pro faktury je již ověřený.',
        'verification_sent' => 'Ověřovací e-mail byl odeslán.',
        'verification_failed' => 'Ověřovací e-mail se nepodařilo odeslat. Zkus to za chvíli znovu.',
        'subscription_cancelled' => 'Předplatné bylo zrušeno.',
        'subscription_active' => 'Předplatné je aktivní.',
        'override_saved' => 'Výjimka byla uložena.',
        'address_added' => 'Adresa byla přidána.',
        'address_updated' => 'Adresa byla upravena.',
        'contact_added' => 'Kontaktní osoba byla přidána.',
        'contact_updated' => 'Kontakt byl upraven.',
    ],

    'product' => [
        'created' => 'Projekt byl vytvořen.',
        'updated' => 'Projekt byl upraven.',
        'in_use' => 'Projekt používají organizace – nejprve je odpoj.',
        'feature_added' => 'Funkce byla přidána do katalogu.',
        'feature_updated' => 'Funkce byla upravena.',
        'plan_created' => 'Plán byl vytvořen.',
        'plan_updated' => 'Plán byl upraven.',
        'plan_in_use' => 'Plán se používá, nejprve přesuň zákazníky jinam.',
    ],

    'invoice' => [
        'draft_created' => 'Koncept dokladu byl vytvořen. Doplň položky a vystav ho.',
        'trashed' => 'Koncept byl přesunut do koše. Najdeš ho ve filtru „Smazané“.',
        'restored' => 'Koncept byl obnoven.',
        'restored_event' => 'Koncept byl obnoven z koše.',
        'force_deleted' => 'Koncept byl trvale smazán.',
        'item_added' => 'Položka byla přidána.',
        'item_updated' => 'Položka byla upravena.',
        'item_removed' => 'Položka byla odstraněna.',
        'reminder_sent' => 'Upomínka byla odeslána.',
        'payment_recorded' => 'Úhrada byla zaznamenána.',
        'cancelled' => 'Doklad byl stornován.',
        'converted' => 'Koncept řádné faktury byl připraven.',
        'duplicated' => 'Kopie byla vytvořena jako nový koncept.',
        'sending_test' => 'Testovací režim – doklady a upomínky jdou na :email, ne zákazníkům.',
        'sending_off' => 'Odesílání dokladů je vypnuté (Faktury → Nastavení).',
        'sending_no_redirect' => 'Testovací režim nemá nastavenou adresu, kam doklady posílat (Faktury → Nastavení).',
    ],

    'webhook_updated' => 'Webhook byl upraven.',

    'contact_types' => [
        'general' => 'Obecný',
        'billing' => 'Fakturace',
        'technical' => 'Technický',
        'statutory' => 'Statutár',
    ],

    'validation' => [
        'quantity_zero' => 'Množství nemůže být nula.',
        'period_end' => 'Konec období nemůže být dříve než jeho začátek.',
        'due_at' => 'Splatnost nemůže být dříve než datum vystavení.',
        'symbol_digits' => ':attribute může obsahovat jen číslice.',
        'attributes' => [
            'quantity' => 'množství',
            'unit_price' => 'jednotková cena',
            'organization_id' => 'organizace',
            'issued_at' => 'datum vystavení',
            'delivered_at' => 'datum dodání',
            'due_at' => 'datum splatnosti',
            'variable_symbol' => 'Variabilní symbol',
            'constant_symbol' => 'Konstantní symbol',
            'specific_symbol' => 'Specifický symbol',
        ],
    ],

    'invoice_errors' => [
        'no_plan' => 'Předplatné nemá přiřazený plán, fakturu nelze vystavit.',
        'already_issued' => 'Doklad je již vystavený.',
        'no_items' => 'Doklad nemá žádné položky.',
        'missing_fields' => 'Firmě chybí fakturační údaje: :fields.',
        'draft_not_payable' => 'Koncept nelze označit jako uhrazený – nejprve ho vystav.',
        'cancelled_not_payable' => 'Stornovaný doklad nelze uhradit.',
        'draft_no_cancel' => 'Koncept není třeba stornovat – prostě ho smaž.',
        'paid_use_credit' => 'Doklad má evidovanou úhradu. Vystav dobropis.',
        'credit_only_invoice' => 'Dobropis se vystavuje jen k řádné faktuře.',
        'credit_not_issued' => 'K nevystavené faktuře nemá dobropis smysl.',
        'credit_no_items' => 'Nejsou vybrány žádné položky k dobropisování.',
        'not_proforma' => 'Zdrojový doklad není zálohová faktura.',
        'issued_locked' => 'Vystavený doklad již nelze měnit. Vystav dobropis.',
        'draft_not_sent' => 'Koncept se neposílá. Nejprve doklad vystav.',
        'not_overdue' => 'Doklad není po splatnosti.',
        'do_not_send' => 'Firma má nastaveno „Doklady neposílat“ – zákazníkovi se nic neodešle.',
    ],

];
