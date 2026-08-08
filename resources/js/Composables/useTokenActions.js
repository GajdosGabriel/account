import { t } from './useLang';

/**
 * Akcie tokenu, ktoré nepatria do štandardného menu (zobraziť/upraviť/kôš).
 *
 * Zrušenie a jeho návrat sú dve strany tej istej mince a v menu sa nikdy
 * neobjavia naraz – rozhoduje ServiceClientPolicy podľa toho, či token
 * zrušený je, alebo nie je. Zoznam tokenov aj stránka úpravy berú
 * položky odtiaľto, aby sa nemohli rozísť.
 */
export function tokenMenu(token) {
    const url = (suffix) => `/developers/tokens/${token.id}${suffix}`;

    return [
        {
            label: t('actions.token.revoke'),
            icon: 'ban',
            can: 'revoke',
            danger: true,
            method: 'post',
            url: url('/revoke'),
            confirm: t('actions.token.confirm.revoke', { name: token.name }),
        },
        {
            // Hash tokenu v databáze zostal, takže projekt pokračuje
            // s tou istou hodnotou – nič si nemusí meniť v konfigurácii.
            label: t('actions.token.unrevoke'),
            icon: 'refresh',
            can: 'unrevoke',
            method: 'post',
            url: url('/unrevoke'),
            confirm: t('actions.token.confirm.unrevoke', { name: token.name }),
        },
    ];
}
