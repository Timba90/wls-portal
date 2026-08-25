{{--
    Die häufigste Fehlerseite im Alltag: die Sitzung endet nach 30 Minuten
    ohne Aktivität (AE-9). Wer ein Formular in einem alten Tab abschickt,
    landet hier — deshalb sagt der Text, was zu tun ist, statt „Page Expired".
--}}
<x-error-page code="419"
              title="Sitzung abgelaufen"
              message="Aus Sicherheitsgründen endet die Anmeldung nach 30 Minuten ohne Aktivität. Bitte erneut anmelden; die Eingaben aus dem alten Tab sind nicht gespeichert."
              action-label="Erneut anmelden"
              :action-url="route('login')" />
