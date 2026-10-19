"""Builds kiscore-de_DE.po (du) and kiscore-de_DE_formal.po (Sie) from kiscore.pot.

Run after `wp i18n make-pot`, then compile with msgfmt (see README.md).
"""
import re
from pathlib import Path

HERE = Path(__file__).parent

# msgid: (formal "Sie", informal "du"); a plain string is the same in both.
DE = {
    "KIscore": "KIscore",
    "https://kiscore.ai": "https://kiscore.ai",
    "Shows in KIscore which AI crawlers visit your site and which visitors come from ChatGPT, Perplexity and other AI assistants. Server-side, without script or cookie.": (
        "Zeigt in KIscore, welche KI-Crawler Ihre Website besuchen und welche Besucher über ChatGPT, Perplexity und andere KI-Assistenten kommen. Serverseitig, ohne Script und Cookie.",
        "Zeigt in KIscore, welche KI-Crawler deine Website besuchen und welche Besucher über ChatGPT, Perplexity und andere KI-Assistenten kommen. Serverseitig, ohne Script und Cookie."),
    "Settings": "Einstellungen",
    "KIscore is active but not connected yet.": "KIscore ist aktiv, aber noch nicht verbunden.",
    "Enter the connection key": "Verbindungsschlüssel eintragen",
    "You are not allowed to do this.": ("Sie dürfen diese Aktion nicht ausführen.", "Du darfst diese Aktion nicht ausführen."),
    "KIscore shows which AI crawlers visit your site and which visitors come from ChatGPT, Perplexity and other AI assistants. The plugin reports page views from your server. It adds no script and no cookie to your pages.": (
        "KIscore zeigt, welche KI-Crawler Ihre Website besuchen und welche Besucher über ChatGPT, Perplexity und andere KI-Assistenten kommen. Das Plugin meldet Seitenaufrufe von Ihrem Server aus. Es fügt Ihren Seiten weder Script noch Cookie hinzu.",
        "KIscore zeigt, welche KI-Crawler deine Website besuchen und welche Besucher über ChatGPT, Perplexity und andere KI-Assistenten kommen. Das Plugin meldet Seitenaufrufe von deinem Server aus. Es fügt deinen Seiten weder Script noch Cookie hinzu."),
    "Connection key": "Verbindungsschlüssel",
    "Copy it from %s in the KIscore dashboard. It stays on your server and is not displayed here again.": (
        "Kopieren Sie ihn im KIscore-Dashboard unter %s. Er bleibt auf Ihrem Server und wird hier nicht wieder angezeigt.",
        "Kopiere ihn im KIscore-Dashboard unter %s. Er bleibt auf deinem Server und wird hier nicht wieder angezeigt."),
    "Settings → Traffic tracking → WordPress": "Einstellungen → Traffic-Tracking → WordPress",
    "Connected to site ID %s.": "Verbunden mit Site-ID %s.",
    "This is not a connection key. Copy it again from the KIscore dashboard; it has the form site-id.secret.": (
        "Das ist kein Verbindungsschlüssel. Kopieren Sie ihn erneut aus dem KIscore-Dashboard, er hat die Form site-id.secret.",
        "Das ist kein Verbindungsschlüssel. Kopiere ihn erneut aus dem KIscore-Dashboard, er hat die Form site-id.secret."),
    "Status": "Status",
    "Last report": "Letzte Meldung",
    "Connection": "Verbindung",
    "Check connection": "Verbindung prüfen",
    "Checks the connection key without sending visitor data.": "Prüft den Verbindungsschlüssel, ohne Besucherdaten zu senden.",
    "Test visit": "Testbesuch",
    "Open any page of your site with %s. The visit then appears in KIscore under KI-Verweise.": (
        "Rufen Sie eine beliebige Seite Ihrer Website mit %s auf. Der Besuch erscheint danach in KIscore unter „KI-Verweise“.",
        "Ruf eine beliebige Seite deiner Website mit %s auf. Der Besuch erscheint danach in KIscore unter „KI-Verweise“."),
    "A page cache is active. Pages served from the cache do not reach WordPress and are not reported, so AI crawlers that only get cached pages can be missing in KIscore.":
        "Ein Seiten-Cache ist aktiv. Seiten aus dem Cache erreichen WordPress nicht und werden nicht gemeldet. KI-Crawler, die nur gecachte Seiten abrufen, können deshalb in KIscore fehlen.",
    "Set in wp-config.php.": "In wp-config.php festgelegt.",
    "Set as environment variable.": "Als Umgebungsvariable festgelegt.",
    "No page view reported yet.": "Noch kein Seitenaufruf gemeldet.",
    "%1$s ago: %2$s": "vor %1$s: %2$s",
    "KIscore accepts data from this site.": "KIscore nimmt Daten dieser Website an.",
    "KIscore rejected the connection key (HTTP 401). Copy it again from the KIscore dashboard.": (
        "KIscore hat den Verbindungsschlüssel abgelehnt (HTTP 401). Kopieren Sie ihn erneut aus dem KIscore-Dashboard.",
        "KIscore hat den Verbindungsschlüssel abgelehnt (HTTP 401). Kopiere ihn erneut aus dem KIscore-Dashboard."),
    "KIscore did not accept the data (HTTP 422). Check that the subscription for this website is active.": (
        "KIscore hat die Daten nicht angenommen (HTTP 422). Prüfen Sie, ob das Abo für diese Website aktiv ist.",
        "KIscore hat die Daten nicht angenommen (HTTP 422). Prüfe, ob das Abo für diese Website aktiv ist."),
    "KIscore is limiting requests from this server right now (HTTP 429).": "KIscore begrenzt gerade die Anfragen dieses Servers (HTTP 429).",
    "KIscore could not be reached: %s": "KIscore ist nicht erreichbar: %s",
    "no answer": "keine Antwort",
    "KIscore answered with HTTP %d.": "KIscore antwortet mit HTTP %d.",
    "WordPress receives requests from %s, a private address. Your site probably runs behind a proxy or CDN. Name the header with the visitor IP in wp-config.php, e.g. define( 'KISCORE_CLIENT_IP_HEADER', 'X-Real-IP' );": (
        "WordPress erhält Anfragen von %s, einer privaten Adresse. Ihre Website läuft vermutlich hinter einem Proxy oder CDN. Nennen Sie den Header mit der Besucher-IP in der wp-config.php, etwa define( 'KISCORE_CLIENT_IP_HEADER', 'X-Real-IP' );",
        "WordPress erhält Anfragen von %s, einer privaten Adresse. Deine Website läuft vermutlich hinter einem Proxy oder CDN. Nenn den Header mit der Besucher-IP in der wp-config.php, etwa define( 'KISCORE_CLIENT_IP_HEADER', 'X-Real-IP' );"),
    "Your current request carries: %s.": ("Ihre aktuelle Anfrage enthält: %s.", "Deine aktuelle Anfrage enthält: %s."),
}

def po_string(value: str) -> str:
    return '"' + value.replace("\\", "\\\\").replace('"', '\\"') + '"'


def build(locale: str, formal: bool) -> None:
    pot = (HERE / "kiscore.pot").read_text()
    header, body = pot.split("\n\n", 1)
    out = [
        "# German translation of the KIscore plugin.",
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: KIscore\\n"',
        f'"Language: {locale}\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
        '"X-Domain: kiscore\\n"',
        "",
    ]
    for block in body.split("\n\n")[1:] if body.startswith('msgid ""') else body.split("\n\n"):
        match = re.search(r'msgid ((?:".*"\n)+)msgstr', block + "\n")
        if not match:
            continue
        msgid = "".join(re.findall(r'"(.*)"', match.group(1))).replace('\\"', '"')
        if not msgid:
            continue
        if msgid not in DE:
            raise SystemExit(f"Missing translation: {msgid!r}")
        value = DE[msgid]
        if isinstance(value, tuple):
            value = value[0] if formal else value[1]
        elif re.search(r"\b(Sie|Ihr\w*|du|dein\w*)\b", value):
            raise SystemExit(f"Addresses the reader, needs a Sie and a du variant: {msgid!r}")
        comments = [line for line in block.splitlines() if line.startswith("#")]
        out += comments + ["msgid " + po_string(msgid), "msgstr " + po_string(value), ""]
    (HERE / f"kiscore-{locale}.po").write_text("\n".join(out))


build("de_DE_formal", formal=True)
build("de_DE", formal=False)
