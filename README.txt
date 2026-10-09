Claude Site Assistant
=====================

Plugin WordPress che aggiunge al sito un chatbot per i visitatori basato
sull'API di Claude (Anthropic). L'assistente risponde usando i contenuti
delle pagine del sito (e, se vuoi, degli ultimi articoli) più le
informazioni che inserisci tu nelle impostazioni. Quando non conosce la
risposta lo dice, senza inventare, e propone i tuoi contatti.

Sviluppato da Pietro Menconi
Sito: https://www.menconi.it
Supporto: cloud@menconi.it

REQUISITI
---------
- WordPress 7.0 o superiore.
- PHP 7.4 o superiore (consigliato PHP 8.3+).
- Un account Anthropic con una API key attiva (uso a consumo).
- Testato fino a WordPress 7.0.2.

INSTALLAZIONE
-------------
1. Plugin > Aggiungi nuovo > Carica plugin, seleziona claude-site-assistant.zip,
   attiva il plugin.
2. Vai su Impostazioni > Claude Site Assistant.
3. Inserisci la API key, compila contatti e informazioni principali, salva.
4. Il plugin parte in MODALITÀ TEST: la chat è visibile solo agli
   amministratori collegati. Provala sul sito con alcune domande reali.
5. Quando le risposte ti convincono, imposta lo stato su "Attivo per tutti".

OTTENERE UNA API KEY CLAUDE (ANTHROPIC)
-----------------------------------------
1. Vai su https://console.anthropic.com/ e accedi.
2. Settings > API Keys > Create Key (es. "chatbot-nomesito"), copiala:
   viene mostrata una sola volta.
3. Consigliato: imposta un limite di spesa mensile nella console.
4. Incollala nel campo "API Key Claude" del plugin. Dopo il salvataggio la
   chiave non viene più mostrata nella pagina.

COME FUNZIONA
-------------
- Base di conoscenza: al salvataggio delle impostazioni (o con il pulsante
  "Aggiorna base di conoscenza") il plugin estrae il testo di tutte le
  pagine pubblicate e degli ultimi N articoli, compresi i contenuti Divi.
  Dopo aver modificato pagine importanti, premi di nuovo il pulsante.
  Nella pagina impostazioni puoi vedere il testo esatto che l'assistente
  conosce. Limite: circa 150.000 caratteri.
- Costi: il testo del sito viene inviato a Claude a ogni domanda, ma con
  il prompt caching dell'API dalla seconda domanda in poi viene letto dalla
  cache a una frazione del prezzo. Haiku 5.5 è il modello più economico,
  Opus 5.5 il più accurato.
- Conversazione: resta nel browser del visitatore (sessionStorage) e si
  azzera chiudendo la scheda o con "Nuova chat". Massimo 10 scambi per
  chat. Il server non salva le conversazioni.
- Limiti anti-abuso: domande per visitatore all'ora (l'IP è usato solo
  come hash, mai salvato in chiaro) e tetto giornaliero complessivo.

PRIVACY (GDPR)
--------------
- Le domande dei visitatori vengono inviate ad Anthropic per generare la
  risposta: va indicato nella privacy policy del sito (Anthropic come
  responsabile del trattamento / fornitore extra-UE).
- Il widget mostra un avviso privacy personalizzabile con il link alla
  privacy policy.
- Nessun cookie: viene usato solo il sessionStorage del browser.

AGGIORNAMENTI
-------------
Stesso meccanismo di Claude Content Generator: indica il repository GitHub
nelle impostazioni; a ogni Release con tag di versione superiore e lo zip
allegato (cartella "claude-site-assistant/...") WordPress mostra il banner
di aggiornamento.

NOTE TECNICHE
-------------
- Endpoint REST: POST /wp-json/csa/v1/chat
- Se il sito è dietro Cloudflare o un proxy, il limite per visitatore vede
  l'IP del proxy: in quel caso conviene affidarsi soprattutto al tetto
  giornaliero, o configurare il server per passare l'IP reale.
