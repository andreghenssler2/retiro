<?php

declare(strict_types=1);

/**
 * Instalador unico: lembretes de PIX e boleto a 3 e 1 dias do vencimento.
 * PHP 8.2+; somente terminal. Nao altera inscricoes ou pagamentos.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit('Instalador disponivel somente pelo terminal.');
}

$raiz = __DIR__;
$verificar = in_array('--verificar', $argv, true);
$ajuda = in_array('--ajuda', $argv, true) || in_array('--help', $argv, true);
if ($ajuda) {
    echo "Uso: php aplicar_alteracao.php [--verificar]\n";
    exit(0);
}

// Conteudos de arquivos da atualizacao, embutidos em base64 para dispensar ZIPs separados.
$arquivosCodificados = [
    'mod/services/LembreteVencimentoEmailService.php' => 'PD9waHAKCmRlY2xhcmUoc3RyaWN0X3R5cGVzPTEpOwoKLyoqCiAqIEF2aXNvcyBwcmV2ZW50aXZvcyBwYXJhIFBJWCBlIGJvbGV0bzogMyBlIDEgZGlhcyBjb3JyaWRvcyBhbnRlcyBkbyB2ZW5jaW1lbnRvLgogKiBFeGVjdXRhZG8gZXhjbHVzaXZhbWVudGUgcGVsbyBjcm9uIHVuaWZpY2FkbyBleGlzdGVudGUsIGNvbSB0cmF2YSBNeVNRTAogKiBlIHVuaWNpZGFkZSAocGFnYW1lbnRvLCB2ZW5jaW1lbnRvLCBhbnRlY2VkZW5jaWEpLgogKi8KZmluYWwgY2xhc3MgTGVtYnJldGVWZW5jaW1lbnRvRW1haWxTZXJ2aWNlCnsKICAgIHByaXZhdGUgY29uc3QgTE9DSyA9ICdyZXRpcm9fbGVtYnJldGVzX3ZlbmNpbWVudG9fdjEnOwogICAgcHJpdmF0ZSBjb25zdCBUQUJFTEEgPSAnZW1haWxfbGVtYnJldGVzX3ZlbmNpbWVudG8nOwogICAgcHJpdmF0ZSBEYXRlVGltZVpvbmUgJGZ1c287CgogICAgcHVibGljIGZ1bmN0aW9uIF9fY29uc3RydWN0KHByaXZhdGUgUERPICRkYikKICAgIHsKICAgICAgICAkdGhpcy0+ZnVzbyA9IG5ldyBEYXRlVGltZVpvbmUoJ0FtZXJpY2EvU2FvX1BhdWxvJyk7CiAgICAgICAgJHRoaXMtPmRiLT5zZXRBdHRyaWJ1dGUoUERPOjpBVFRSX0VSUk1PREUsIFBETzo6RVJSTU9ERV9FWENFUFRJT04pOwogICAgICAgICR0aGlzLT5kYi0+c2V0QXR0cmlidXRlKFBETzo6QVRUUl9ERUZBVUxUX0ZFVENIX01PREUsIFBETzo6RkVUQ0hfQVNTT0MpOwogICAgfQoKICAgIC8qKiBEaWFzIGNvcnJpZG9zIHJlc3RhbnRlczsgbnVsbCBwYXJhIGRhdGFzIGludmFsaWRhcy4gKi8KICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gZGlhc0F0ZVZlbmNpbWVudG8oc3RyaW5nICR2ZW5jaW1lbnRvLCBzdHJpbmcgJGRhdGFSZWZlcmVuY2lhKTogP2ludAogICAgewogICAgICAgICRmdXNvID0gbmV3IERhdGVUaW1lWm9uZSgnQW1lcmljYS9TYW9fUGF1bG8nKTsKICAgICAgICAkdiA9IERhdGVUaW1lSW1tdXRhYmxlOjpjcmVhdGVGcm9tRm9ybWF0KCchWS1tLWQnLCAkdmVuY2ltZW50bywgJGZ1c28pOwogICAgICAgICRoID0gRGF0ZVRpbWVJbW11dGFibGU6OmNyZWF0ZUZyb21Gb3JtYXQoJyFZLW0tZCcsICRkYXRhUmVmZXJlbmNpYSwgJGZ1c28pOwogICAgICAgIGlmICghJHYgfHwgISRoIHx8ICR2LT5mb3JtYXQoJ1ktbS1kJykgIT09ICR2ZW5jaW1lbnRvIHx8ICRoLT5mb3JtYXQoJ1ktbS1kJykgIT09ICRkYXRhUmVmZXJlbmNpYSkgewogICAgICAgICAgICByZXR1cm4gbnVsbDsKICAgICAgICB9CiAgICAgICAgcmV0dXJuIChpbnQpICRoLT5kaWZmKCR2KS0+Zm9ybWF0KCclciVhJyk7CiAgICB9CgogICAgLyoqIEByZXR1cm4gYXJyYXk8c3RyaW5nLGludHxzdHJpbmd8Ym9vbD4gKi8KICAgIHB1YmxpYyBmdW5jdGlvbiBwcm9jZXNzYXIoKTogYXJyYXkKICAgIHsKICAgICAgICAkcmV0b3JubyA9IFsnYXRpdm8nID0+IHRydWUsICdjYW5kaWRhdG9zJyA9PiAwLCAnZW52aWFkb3MnID0+IDAsICdmYWxoYXMnID0+IDAsICdpZ25vcmFkb3MnID0+IDBdOwogICAgICAgICRjZmcgPSAkdGhpcy0+Y29uZmlndXJhY2FvKCk7CiAgICAgICAgaWYgKCEoJGNmZ1snYXRpdm8nXSA/PyB0cnVlKSkgewogICAgICAgICAgICAkcmV0b3Jub1snYXRpdm8nXSA9IGZhbHNlOwogICAgICAgICAgICAkcmV0b3Jub1snbW90aXZvJ10gPSAnRGVzYXRpdmFkbyBuYSBjb25maWd1cmFjYW8nOwogICAgICAgICAgICByZXR1cm4gJHJldG9ybm87CiAgICAgICAgfQogICAgICAgIGlmICghJHRoaXMtPnBlcm1pdGVFbnZpb05lc3RlQW1iaWVudGUoJGNmZykpIHsKICAgICAgICAgICAgJHJldG9ybm9bJ2F0aXZvJ10gPSBmYWxzZTsKICAgICAgICAgICAgJHJldG9ybm9bJ21vdGl2byddID0gJ0VudmlvIGJsb3F1ZWFkbyBubyBhbWJpZW50ZSBsb2NhbC9YQU1QUCc7CiAgICAgICAgICAgIHJldHVybiAkcmV0b3JubzsKICAgICAgICB9CiAgICAgICAgaWYgKCEkdGhpcy0+dGFiZWxhRXhpc3RlKCkpIHsKICAgICAgICAgICAgdGhyb3cgbmV3IFJ1bnRpbWVFeGNlcHRpb24oJ1RhYmVsYSBlbWFpbF9sZW1icmV0ZXNfdmVuY2ltZW50byBhdXNlbnRlLiBFeGVjdXRlIGFwbGljYXJfYWx0ZXJhY2FvLnBocC4nKTsKICAgICAgICB9CgogICAgICAgICRvYnRpZG8gPSAoaW50KSAkdGhpcy0+ZGItPnF1ZXJ5KCdTRUxFQ1QgR0VUX0xPQ0soJyAuICR0aGlzLT5kYi0+cXVvdGUoc2VsZjo6TE9DSykgLiAnLCAwKScpLT5mZXRjaENvbHVtbigpOwogICAgICAgIGlmICgkb2J0aWRvICE9PSAxKSB7CiAgICAgICAgICAgICRyZXRvcm5vWydvY3VwYWRvJ10gPSB0cnVlOwogICAgICAgICAgICByZXR1cm4gJHJldG9ybm87CiAgICAgICAgfQoKICAgICAgICB0cnkgewogICAgICAgICAgICAkaG9qZSA9IG5ldyBEYXRlVGltZUltbXV0YWJsZSgndG9kYXknLCAkdGhpcy0+ZnVzbyk7CiAgICAgICAgICAgICRsaW1pdGUgPSBtYXgoMSwgbWluKDIwLCAoaW50KSAoJGNmZ1snbGltaXRlX3Bvcl9leGVjdWNhbyddID8/IDgpKSk7CiAgICAgICAgICAgICRwYWdhbWVudG9zID0gJHRoaXMtPmxpc3RhckNhbmRpZGF0b3MoJGhvamUsICRsaW1pdGUpOwogICAgICAgICAgICAkcmV0b3Jub1snY2FuZGlkYXRvcyddID0gY291bnQoJHBhZ2FtZW50b3MpOwoKICAgICAgICAgICAgZm9yZWFjaCAoJHBhZ2FtZW50b3MgYXMgJHApIHsKICAgICAgICAgICAgICAgICRpZCA9IChpbnQpICRwWydpZFBhZ2FtZW50byddOwogICAgICAgICAgICAgICAgJHZlbmNpbWVudG8gPSAoc3RyaW5nKSAkcFsnZGF0YVZlbmNpbWVudG8nXTsKICAgICAgICAgICAgICAgICRkaWFzID0gc2VsZjo6ZGlhc0F0ZVZlbmNpbWVudG8oJHZlbmNpbWVudG8sICRob2plLT5mb3JtYXQoJ1ktbS1kJykpOwogICAgICAgICAgICAgICAgaWYgKCFpbl9hcnJheSgkZGlhcywgWzEsIDNdLCB0cnVlKSkgewogICAgICAgICAgICAgICAgICAgICRyZXRvcm5vWydpZ25vcmFkb3MnXSsrOwogICAgICAgICAgICAgICAgICAgIGNvbnRpbnVlOwogICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgJGVtYWlsID0gdHJpbSgoc3RyaW5nKSAoJHBbJ2VtYWlsVXN1YXJpbyddID8/ICcnKSk7CiAgICAgICAgICAgICAgICAkbm9tZSA9IHRyaW0oKHN0cmluZykgKCRwWydub21lVXN1YXJpbyddID8/ICcnKSk7CiAgICAgICAgICAgICAgICBpZiAoJG5vbWUgPT09ICcnKSB7CiAgICAgICAgICAgICAgICAgICAgJG5vbWUgPSAnUGFydGljaXBhbnRlJzsKICAgICAgICAgICAgICAgIH0KCiAgICAgICAgICAgICAgICAvLyBSZXNlcnZhIHRlbnRhdGl2YXMgZSBwZXJtaXRlIHJlcGV0aXIgYXBvcyAxNSBtaW51dG9zIHNlIGhvdXZlciBmYWxoYS4KICAgICAgICAgICAgICAgICR0aGlzLT5yZWdpc3RyYXJUZW50YXRpdmEoJGlkLCAkdmVuY2ltZW50bywgJGRpYXMpOwogICAgICAgICAgICAgICAgdHJ5IHsKICAgICAgICAgICAgICAgICAgICAvLyBDb25mZXJlIG5vdmFtZW50ZSBhbnRlcyBkZSBlbnZpYXI6IHVtIHdlYmhvb2sgcG9kZSB0ZXIgY29uZmlybWFkbwogICAgICAgICAgICAgICAgICAgIC8vIG8gcGFnYW1lbnRvIGVucXVhbnRvIGVzdGUgbG90ZSBlc3RhdmEgc2VuZG8gcHJlcGFyYWRvLgogICAgICAgICAgICAgICAgICAgIGlmICghJHRoaXMtPmFpbmRhUGVuZGVudGUoJGlkLCAkdmVuY2ltZW50bykpIHsKICAgICAgICAgICAgICAgICAgICAgICAgJHJldG9ybm9bJ2lnbm9yYWRvcyddKys7CiAgICAgICAgICAgICAgICAgICAgICAgIGNvbnRpbnVlOwogICAgICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgICAgICAgICBpZiAoIWZpbHRlcl92YXIoJGVtYWlsLCBGSUxURVJfVkFMSURBVEVfRU1BSUwpKSB7CiAgICAgICAgICAgICAgICAgICAgICAgIHRocm93IG5ldyBSdW50aW1lRXhjZXB0aW9uKCdOYW8gZXhpc3RlIGUtbWFpbCB2YWxpZG8gcGFyYSBvIGRlc3RpbmF0YXJpby4nKTsKICAgICAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgICAgICAgICAgJGFzc3VudG8gPSBzcHJpbnRmKAogICAgICAgICAgICAgICAgICAgICAgICAnTGVtYnJldGU6IHBhZ2FtZW50byB2ZW5jZSBlbSAlZCAlcyAtICVzJywKICAgICAgICAgICAgICAgICAgICAgICAgJGRpYXMsCiAgICAgICAgICAgICAgICAgICAgICAgICRkaWFzID09PSAxID8gJ2RpYScgOiAnZGlhcycsCiAgICAgICAgICAgICAgICAgICAgICAgIChzdHJpbmcpICRwWydldmVudG8nXQogICAgICAgICAgICAgICAgICAgICk7CiAgICAgICAgICAgICAgICAgICAgJGh0bWwgPSAkdGhpcy0+cmVuZGVyaXphcigkcCwgJG5vbWUsICRkaWFzKTsKICAgICAgICAgICAgICAgICAgICByZXF1aXJlX29uY2UgZGlybmFtZShfX0RJUl9fKSAuICcvbWFpbC9NYWlsLnBocCc7CiAgICAgICAgICAgICAgICAgICAgJGVudmlhZG8gPSAobmV3IE1haWwoKSktPnNlbmQoJGVtYWlsLCAkbm9tZSwgJGFzc3VudG8sICRodG1sKTsKICAgICAgICAgICAgICAgICAgICBpZiAoISRlbnZpYWRvKSB7CiAgICAgICAgICAgICAgICAgICAgICAgIHRocm93IG5ldyBSdW50aW1lRXhjZXB0aW9uKCdTZXJ2aWNvIGRlIGUtbWFpbCByZXRvcm5vdSBmYWxoYSBubyBlbnZpby4nKTsKICAgICAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgICAgICAgICAgJHRoaXMtPmF0dWFsaXphclRlbnRhdGl2YSgkaWQsICR2ZW5jaW1lbnRvLCAkZGlhcywgdHJ1ZSwgbnVsbCk7CiAgICAgICAgICAgICAgICAgICAgJHJldG9ybm9bJ2VudmlhZG9zJ10rKzsKICAgICAgICAgICAgICAgIH0gY2F0Y2ggKFRocm93YWJsZSAkZXJybykgewogICAgICAgICAgICAgICAgICAgICR0aGlzLT5hdHVhbGl6YXJUZW50YXRpdmEoJGlkLCAkdmVuY2ltZW50bywgJGRpYXMsIGZhbHNlLCAkZXJyby0+Z2V0TWVzc2FnZSgpKTsKICAgICAgICAgICAgICAgICAgICAkcmV0b3Jub1snZmFsaGFzJ10rKzsKICAgICAgICAgICAgICAgICAgICBlcnJvcl9sb2coJ1tSZXRpcm9dIEZhbGhhIG5vIGxlbWJyZXRlIGRlIHBhZ2FtZW50byAjJyAuICRpZCAuICc6ICcgLiAkZXJyby0+Z2V0TWVzc2FnZSgpKTsKICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgfQogICAgICAgIH0gZmluYWxseSB7CiAgICAgICAgICAgICR0aGlzLT5kYi0+cXVlcnkoJ1NFTEVDVCBSRUxFQVNFX0xPQ0soJyAuICR0aGlzLT5kYi0+cXVvdGUoc2VsZjo6TE9DSykgLiAnKScpOwogICAgICAgIH0KICAgICAgICByZXR1cm4gJHJldG9ybm87CiAgICB9CgogICAgLyoqIExpc3RhZ2VtIHJlYWQtb25seSBwYXJhIGRpYWdub3N0aWNvLCBzZW0gZW52aWFyIG5lbSBtYXJjYXIgZS1tYWlscy4gKi8KICAgIHB1YmxpYyBmdW5jdGlvbiBzaW11bGFyKCk6IGFycmF5CiAgICB7CiAgICAgICAgaWYgKCEkdGhpcy0+dGFiZWxhRXhpc3RlKCkpIHsKICAgICAgICAgICAgdGhyb3cgbmV3IFJ1bnRpbWVFeGNlcHRpb24oJ1RhYmVsYSBlbWFpbF9sZW1icmV0ZXNfdmVuY2ltZW50byBhdXNlbnRlLicpOwogICAgICAgIH0KICAgICAgICAkaG9qZSA9IG5ldyBEYXRlVGltZUltbXV0YWJsZSgndG9kYXknLCAkdGhpcy0+ZnVzbyk7CiAgICAgICAgJGNhbmRpZGF0b3MgPSAkdGhpcy0+bGlzdGFyQ2FuZGlkYXRvcygkaG9qZSwgNTAwKTsKICAgICAgICAkcmVzdW1vID0gWydkYXRhJyA9PiAkaG9qZS0+Zm9ybWF0KCdkL20vWScpLCAnZmFsdGFtXzNfZGlhcycgPT4gMCwgJ2ZhbHRhXzFfZGlhJyA9PiAwXTsKICAgICAgICBmb3JlYWNoICgkY2FuZGlkYXRvcyBhcyAkcCkgewogICAgICAgICAgICAkZGlhcyA9IHNlbGY6OmRpYXNBdGVWZW5jaW1lbnRvKChzdHJpbmcpICRwWydkYXRhVmVuY2ltZW50byddLCAkaG9qZS0+Zm9ybWF0KCdZLW0tZCcpKTsKICAgICAgICAgICAgaWYgKCRkaWFzID09PSAzKSB7ICRyZXN1bW9bJ2ZhbHRhbV8zX2RpYXMnXSsrOyB9CiAgICAgICAgICAgIGlmICgkZGlhcyA9PT0gMSkgeyAkcmVzdW1vWydmYWx0YV8xX2RpYSddKys7IH0KICAgICAgICB9CiAgICAgICAgJHJlc3Vtb1snY2FuZGlkYXRvc19hdGVfNTAwJ10gPSBjb3VudCgkY2FuZGlkYXRvcyk7CiAgICAgICAgcmV0dXJuICRyZXN1bW87CiAgICB9CgogICAgLyoqIEByZXR1cm4gYXJyYXk8aW50LGFycmF5PHN0cmluZyxtaXhlZD4+ICovCiAgICBwcml2YXRlIGZ1bmN0aW9uIGxpc3RhckNhbmRpZGF0b3MoRGF0ZVRpbWVJbW11dGFibGUgJGhvamUsIGludCAkbGltaXRlKTogYXJyYXkKICAgIHsKICAgICAgICAkZGlhMSA9ICRob2plLT5tb2RpZnkoJysxIGRheScpLT5mb3JtYXQoJ1ktbS1kJyk7CiAgICAgICAgJGRpYTMgPSAkaG9qZS0+bW9kaWZ5KCcrMyBkYXlzJyktPmZvcm1hdCgnWS1tLWQnKTsKICAgICAgICAkbGltaXRlID0gbWF4KDEsIG1pbig1MDAsICRsaW1pdGUpKTsKICAgICAgICAkc3FsID0gIlNFTEVDVCBwLmlkUGFnYW1lbnRvLCBwLmRhdGFWZW5jaW1lbnRvLCBwLmZvcm1hUGFnYW1lbnRvLCBwLnZhbG9yLAogICAgICAgICAgICAgICAgICAgICAgIHAuaW52b2ljZVVybCwgcC5iYW5rU2xpcFVybCwgcC5waXhDb3BpYUNvbGEsIHAucGl4RXhwaXJhY2FvLAogICAgICAgICAgICAgICAgICAgICAgIHAuYm9sZXRvTGluaGFEaWdpdGF2ZWwsIGUudGl0dWxvIEFTIGV2ZW50bywKICAgICAgICAgICAgICAgICAgICAgICBDT0FMRVNDRShOVUxMSUYoVFJJTSh1Lm5vbWUpLCAnJyksIE5VTExJRihUUklNKGkubm9tZSksICcnKSwgTlVMTElGKFRSSU0ocC5wYXJ0aWNpcGFudGUpLCAnJyksICdQYXJ0aWNpcGFudGUnKSBBUyBub21lVXN1YXJpbywKICAgICAgICAgICAgICAgICAgICAgICBDT0FMRVNDRShOVUxMSUYoVFJJTSh1LmVtYWlsKSwgJycpLCBOVUxMSUYoVFJJTShpLmVtYWlsKSwgJycpLCBOVUxMSUYoVFJJTShwLmVtYWlsKSwgJycpLCAnJykgQVMgZW1haWxVc3VhcmlvCiAgICAgICAgICAgICAgICBGUk9NIHBhZ2FtZW50b3MgcAogICAgICAgICAgICAgICAgSU5ORVIgSk9JTiBpbnNjcmljb2VzIGkgT04gaS5pZEluc2NyaWNhbyA9IHAuaWRJbnNjcmljYW8KICAgICAgICAgICAgICAgIElOTkVSIEpPSU4gZXZlbnRvcyBlIE9OIGUuaWRFdmVudG8gPSBpLmlkRXZlbnRvCiAgICAgICAgICAgICAgICBMRUZUIEpPSU4gdXN1YXJpb3MgdSBPTiB1LmlkID0gaS5pZFVzdWFyaW8KICAgICAgICAgICAgICAgIExFRlQgSk9JTiBlbWFpbF9sZW1icmV0ZXNfdmVuY2ltZW50byBsCiAgICAgICAgICAgICAgICAgIE9OIGwuaWRQYWdhbWVudG8gPSBwLmlkUGFnYW1lbnRvCiAgICAgICAgICAgICAgICAgQU5EIGwudmVuY2ltZW50byA9IHAuZGF0YVZlbmNpbWVudG8KICAgICAgICAgICAgICAgICBBTkQgbC5kaWFzQW50ZXMgPSBDQVNFIFdIRU4gcC5kYXRhVmVuY2ltZW50byA9IDpkaWExX2pvaW4gVEhFTiAxIEVMU0UgMyBFTkQKICAgICAgICAgICAgICAgIFdIRVJFIHAuc3RhdHVzID0gJ1BlbmRlbnRlJwogICAgICAgICAgICAgICAgICBBTkQgcC5mb3JtYVBhZ2FtZW50byBJTiAoJ1BJWCcsICdCb2xldG8nKQogICAgICAgICAgICAgICAgICBBTkQgcC5kYXRhUGFnYW1lbnRvIElTIE5VTEwKICAgICAgICAgICAgICAgICAgQU5EIHAuZGF0YVZlbmNpbWVudG8gSU4gKDpkaWExLCA6ZGlhMykKICAgICAgICAgICAgICAgICAgQU5EIGkuc3RhdHVzIDw+ICdDYW5jZWxhZGEnCiAgICAgICAgICAgICAgICAgIEFORCBDT0FMRVNDRShwLnZhbG9yLCAwKSA+IDAKICAgICAgICAgICAgICAgICAgQU5EIChsLmlkTGVtYnJldGUgSVMgTlVMTCBPUgogICAgICAgICAgICAgICAgICAgICAgIChsLnN0YXR1cyBJTiAoJ0ZhbGhvdScsICdQcm9jZXNzYW5kbycpIEFORCBsLnRlbnRhdGl2YXMgPCA1CiAgICAgICAgICAgICAgICAgICAgICAgIEFORCBsLnRlbnRhdGl2YUVtIDw9IERBVEVfU1VCKE5PVygpLCBJTlRFUlZBTCAxNSBNSU5VVEUpKSkKICAgICAgICAgICAgICAgIE9SREVSIEJZIHAuZGF0YVZlbmNpbWVudG8gQVNDLCBwLmlkUGFnYW1lbnRvIEFTQwogICAgICAgICAgICAgICAgTElNSVQgeyRsaW1pdGV9IjsKICAgICAgICAkc3RtdCA9ICR0aGlzLT5kYi0+cHJlcGFyZSgkc3FsKTsKICAgICAgICAkc3RtdC0+ZXhlY3V0ZShbJzpkaWExX2pvaW4nID0+ICRkaWExLCAnOmRpYTEnID0+ICRkaWExLCAnOmRpYTMnID0+ICRkaWEzXSk7CiAgICAgICAgcmV0dXJuICRzdG10LT5mZXRjaEFsbChQRE86OkZFVENIX0FTU09DKTsKICAgIH0KCiAgICBwcml2YXRlIGZ1bmN0aW9uIGFpbmRhUGVuZGVudGUoaW50ICRpZCwgc3RyaW5nICR2ZW5jaW1lbnRvKTogYm9vbAogICAgewogICAgICAgICRzdG10ID0gJHRoaXMtPmRiLT5wcmVwYXJlKCJTRUxFQ1QgMSBGUk9NIHBhZ2FtZW50b3MKICAgICAgICAgICAgV0hFUkUgaWRQYWdhbWVudG8gPSA6aWQgQU5EIHN0YXR1cyA9ICdQZW5kZW50ZScKICAgICAgICAgICAgICBBTkQgZGF0YVBhZ2FtZW50byBJUyBOVUxMIEFORCBkYXRhVmVuY2ltZW50byA9IDp2ZW5jaW1lbnRvCiAgICAgICAgICAgICAgQU5EIGZvcm1hUGFnYW1lbnRvIElOICgnUElYJywgJ0JvbGV0bycpIExJTUlUIDEiKTsKICAgICAgICAkc3RtdC0+ZXhlY3V0ZShbJzppZCcgPT4gJGlkLCAnOnZlbmNpbWVudG8nID0+ICR2ZW5jaW1lbnRvXSk7CiAgICAgICAgcmV0dXJuICRzdG10LT5mZXRjaENvbHVtbigpICE9PSBmYWxzZTsKICAgIH0KCiAgICBwcml2YXRlIGZ1bmN0aW9uIHJlZ2lzdHJhclRlbnRhdGl2YShpbnQgJGlkLCBzdHJpbmcgJGRhdGEsIGludCAkZGlhcyk6IHZvaWQKICAgIHsKICAgICAgICAkc3RtdCA9ICR0aGlzLT5kYi0+cHJlcGFyZSgiSU5TRVJUIElOVE8gZW1haWxfbGVtYnJldGVzX3ZlbmNpbWVudG8KICAgICAgICAgICAgKGlkUGFnYW1lbnRvLCB2ZW5jaW1lbnRvLCBkaWFzQW50ZXMsIHN0YXR1cywgdGVudGF0aXZhcywgdGVudGF0aXZhRW0pCiAgICAgICAgICAgIFZBTFVFUyAoOmlkLCA6dmVuY2ltZW50bywgOmRpYXMsICdQcm9jZXNzYW5kbycsIDEsIE5PVygpKQogICAgICAgICAgICBPTiBEVVBMSUNBVEUgS0VZIFVQREFURSBzdGF0dXMgPSAnUHJvY2Vzc2FuZG8nLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB0ZW50YXRpdmFzID0gdGVudGF0aXZhcyArIDEsCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHRlbnRhdGl2YUVtID0gTk9XKCksIHVsdGltYUZhbGhhID0gTlVMTCIpOwogICAgICAgICRzdG10LT5leGVjdXRlKFsnOmlkJyA9PiAkaWQsICc6dmVuY2ltZW50bycgPT4gJGRhdGEsICc6ZGlhcycgPT4gJGRpYXNdKTsKICAgIH0KCiAgICBwcml2YXRlIGZ1bmN0aW9uIGF0dWFsaXphclRlbnRhdGl2YShpbnQgJGlkLCBzdHJpbmcgJGRhdGEsIGludCAkZGlhcywgYm9vbCAkZW52aWFkbywgP3N0cmluZyAkZXJybyk6IHZvaWQKICAgIHsKICAgICAgICAkc3RtdCA9ICR0aGlzLT5kYi0+cHJlcGFyZSgiVVBEQVRFIGVtYWlsX2xlbWJyZXRlc192ZW5jaW1lbnRvCiAgICAgICAgICAgICBTRVQgc3RhdHVzID0gOnN0YXR1cywKICAgICAgICAgICAgICAgICBlbnZpYWRvRW0gPSBDQVNFIFdIRU4gOnN1Y2Vzc28gPSAxIFRIRU4gTk9XKCkgRUxTRSBlbnZpYWRvRW0gRU5ELAogICAgICAgICAgICAgICAgIHVsdGltYUZhbGhhID0gOmVycm8KICAgICAgICAgICAgIFdIRVJFIGlkUGFnYW1lbnRvID0gOmlkIEFORCB2ZW5jaW1lbnRvID0gOnZlbmNpbWVudG8gQU5EIGRpYXNBbnRlcyA9IDpkaWFzIik7CiAgICAgICAgJHN0bXQtPmV4ZWN1dGUoWwogICAgICAgICAgICAnOnN0YXR1cycgPT4gJGVudmlhZG8gPyAnRW52aWFkbycgOiAnRmFsaG91JywKICAgICAgICAgICAgJzpzdWNlc3NvJyA9PiAkZW52aWFkbyA/IDEgOiAwLAogICAgICAgICAgICAnOmVycm8nID0+ICRlcnJvICE9PSBudWxsID8gc3Vic3RyKCRlcnJvLCAwLCA0OTApIDogbnVsbCwKICAgICAgICAgICAgJzppZCcgPT4gJGlkLCAnOnZlbmNpbWVudG8nID0+ICRkYXRhLCAnOmRpYXMnID0+ICRkaWFzLAogICAgICAgIF0pOwogICAgfQoKICAgIHByaXZhdGUgZnVuY3Rpb24gcmVuZGVyaXphcihhcnJheSAkcCwgc3RyaW5nICRub21lLCBpbnQgJGRpYXMpOiBzdHJpbmcKICAgIHsKICAgICAgICAkdmVuY2ltZW50byA9IChzdHJpbmcpICRwWydkYXRhVmVuY2ltZW50byddOwogICAgICAgICRkYXRhVmVuY2ltZW50byA9IERhdGVUaW1lSW1tdXRhYmxlOjpjcmVhdGVGcm9tRm9ybWF0KCchWS1tLWQnLCAkdmVuY2ltZW50bywgJHRoaXMtPmZ1c28pOwogICAgICAgICR1cmwgPSAkdGhpcy0+bGlua1BhZ2FtZW50bygkcCk7CiAgICAgICAgJGZvcm1hID0gKHN0cmluZykgJHBbJ2Zvcm1hUGFnYW1lbnRvJ107CiAgICAgICAgJHBpeENvcGlhQ29sYSA9IHRyaW0oKHN0cmluZykgKCRwWydwaXhDb3BpYUNvbGEnXSA/PyAnJykpOwogICAgICAgICRleHBpcmFjYW9UZXh0byA9IHRyaW0oKHN0cmluZykgKCRwWydwaXhFeHBpcmFjYW8nXSA/PyAnJykpOwogICAgICAgIGlmICgkZXhwaXJhY2FvVGV4dG8gIT09ICcnKSB7CiAgICAgICAgICAgIHRyeSB7CiAgICAgICAgICAgICAgICBpZiAobmV3IERhdGVUaW1lSW1tdXRhYmxlKCRleHBpcmFjYW9UZXh0bywgJHRoaXMtPmZ1c28pIDw9IG5ldyBEYXRlVGltZUltbXV0YWJsZSgibm93IiwgJHRoaXMtPmZ1c28pKSB7CiAgICAgICAgICAgICAgICAgICAgJHBpeENvcGlhQ29sYSA9ICcnOyAvLyBOdW5jYSBpbmNlbnRpdmFyIHBhZ2FtZW50byBjb20gY29kaWdvIFBJWCBleHBpcmFkby4KICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgfSBjYXRjaCAoVGhyb3dhYmxlKSB7CiAgICAgICAgICAgICAgICAkcGl4Q29waWFDb2xhID0gJyc7CiAgICAgICAgICAgIH0KICAgICAgICB9CiAgICAgICAgJGJvbGV0b0xpbmhhRGlnaXRhdmVsID0gdHJpbSgoc3RyaW5nKSAoJHBbJ2JvbGV0b0xpbmhhRGlnaXRhdmVsJ10gPz8gJycpKTsKICAgICAgICAkZXZlbnRvID0gKHN0cmluZykgKCRwWydldmVudG8nXSA/PyAnJyk7CiAgICAgICAgJHZhbG9yID0gJ1IkICcgLiBudW1iZXJfZm9ybWF0KChmbG9hdCkgKCRwWyd2YWxvciddID8/IDApLCAyLCAnLCcsICcuJyk7CiAgICAgICAgJGRhdGEgPSAkZGF0YVZlbmNpbWVudG8gPyAkZGF0YVZlbmNpbWVudG8tPmZvcm1hdCgnZC9tL1knKSA6ICR2ZW5jaW1lbnRvOwogICAgICAgIG9iX3N0YXJ0KCk7CiAgICAgICAgdHJ5IHsKICAgICAgICAgICAgcmVxdWlyZSBkaXJuYW1lKF9fRElSX18pIC4gJy9tYWlsL3RlbXBsYXRlcy9sZW1icmV0ZV92ZW5jaW1lbnRvLnBocCc7CiAgICAgICAgICAgIHJldHVybiAoc3RyaW5nKSBvYl9nZXRfY2xlYW4oKTsKICAgICAgICB9IGNhdGNoIChUaHJvd2FibGUgJGVycm8pIHsKICAgICAgICAgICAgb2JfZW5kX2NsZWFuKCk7CiAgICAgICAgICAgIHRocm93ICRlcnJvOwogICAgICAgIH0KICAgIH0KCiAgICBwcml2YXRlIGZ1bmN0aW9uIGxpbmtQYWdhbWVudG8oYXJyYXkgJHApOiBzdHJpbmcKICAgIHsKICAgICAgICAkZm9ybWEgPSAoc3RyaW5nKSAoJHBbJ2Zvcm1hUGFnYW1lbnRvJ10gPz8gJycpOwogICAgICAgICRjYW5kaWRhdG9zID0gJGZvcm1hID09PSAnQm9sZXRvJwogICAgICAgICAgICA/IFskcFsnYmFua1NsaXBVcmwnXSA/PyAnJywgJHBbJ2ludm9pY2VVcmwnXSA/PyAnJ10KICAgICAgICAgICAgOiBbJHBbJ2ludm9pY2VVcmwnXSA/PyAnJ107CiAgICAgICAgZm9yZWFjaCAoJGNhbmRpZGF0b3MgYXMgJGNhbmRpZGF0bykgewogICAgICAgICAgICAkdXJsID0gdHJpbSgoc3RyaW5nKSAkY2FuZGlkYXRvKTsKICAgICAgICAgICAgaWYgKGZpbHRlcl92YXIoJHVybCwgRklMVEVSX1ZBTElEQVRFX1VSTCkKICAgICAgICAgICAgICAgICYmIHN0cnRvbG93ZXIoKHN0cmluZykgcGFyc2VfdXJsKCR1cmwsIFBIUF9VUkxfU0NIRU1FKSkgPT09ICdodHRwcycpIHsKICAgICAgICAgICAgICAgIHJldHVybiAkdXJsOwogICAgICAgICAgICB9CiAgICAgICAgfQogICAgICAgIHJldHVybiAnaHR0cHM6Ly9yZXRpcm8uaWVjbGJwYXJvYmUuY29tLmJyL3VzZXIvcGFnYW1lbnRvcy5waHAnOwogICAgfQoKICAgIHByaXZhdGUgZnVuY3Rpb24gdGFiZWxhRXhpc3RlKCk6IGJvb2wKICAgIHsKICAgICAgICAkc3RtdCA9ICR0aGlzLT5kYi0+cXVlcnkoIlNIT1cgVEFCTEVTIExJS0UgJ2VtYWlsX2xlbWJyZXRlc192ZW5jaW1lbnRvJyIpOwogICAgICAgIHJldHVybiAkc3RtdC0+ZmV0Y2hDb2x1bW4oKSAhPT0gZmFsc2U7CiAgICB9CgogICAgLyoqIEByZXR1cm4gYXJyYXk8c3RyaW5nLG1peGVkPiAqLwogICAgcHJpdmF0ZSBmdW5jdGlvbiBjb25maWd1cmFjYW8oKTogYXJyYXkKICAgIHsKICAgICAgICAkYXJxdWl2byA9IGRpcm5hbWUoX19ESVJfXywgMikgLiAnL2NvbmZpZy9sZW1icmV0ZXNfcGFnYW1lbnRvLnBocCc7CiAgICAgICAgcmV0dXJuIGlzX2ZpbGUoJGFycXVpdm8pID8gKGFycmF5KSByZXF1aXJlICRhcnF1aXZvIDogW107CiAgICB9CgogICAgcHJpdmF0ZSBmdW5jdGlvbiBwZXJtaXRlRW52aW9OZXN0ZUFtYmllbnRlKGFycmF5ICRjZmcpOiBib29sCiAgICB7CiAgICAgICAgaWYgKCRjZmdbJ3Blcm1pdGlyX2VudmlvX2xvY2FsJ10gPz8gZmFsc2UpIHsgcmV0dXJuIHRydWU7IH0KICAgICAgICAkcmFpeiA9IHN0cl9yZXBsYWNlKCdcXCcsICcvJywgZGlybmFtZShfX0RJUl9fLCAyKSk7CiAgICAgICAgaWYgKFBIUF9PU19GQU1JTFkgPT09ICdXaW5kb3dzJyB8fCBwcmVnX21hdGNoKCd+Lyg/OnhhbXBwfHdhbXB8bGFyYWdvbnxtYW1wKS9+aScsICRyYWl6KSkgewogICAgICAgICAgICByZXR1cm4gZmFsc2U7CiAgICAgICAgfQogICAgICAgIHJldHVybiB0cnVlOwogICAgfQp9Cg==',
    'mod/mail/templates/lembrete_vencimento.php' => 'PCFkb2N0eXBlIGh0bWw+CjxodG1sIGxhbmc9InB0LUJSIj4KPGhlYWQ+PG1ldGEgY2hhcnNldD0iVVRGLTgiPjx0aXRsZT5MZW1icmV0ZSBkZSB2ZW5jaW1lbnRvPC90aXRsZT48L2hlYWQ+Cjxib2R5IHN0eWxlPSJtYXJnaW46MDtwYWRkaW5nOjI0cHg7YmFja2dyb3VuZDojZjVmNmZhO2ZvbnQtZmFtaWx5OkFyaWFsLEhlbHZldGljYSxzYW5zLXNlcmlmO2NvbG9yOiMyNjMyMzgiPgo8dGFibGUgcm9sZT0icHJlc2VudGF0aW9uIiB3aWR0aD0iMTAwJSIgY2VsbHBhZGRpbmc9IjAiIGNlbGxzcGFjaW5nPSIwIj48dHI+PHRkIGFsaWduPSJjZW50ZXIiPgo8dGFibGUgcm9sZT0icHJlc2VudGF0aW9uIiB3aWR0aD0iNjIwIiBjZWxscGFkZGluZz0iMCIgY2VsbHNwYWNpbmc9IjAiIHN0eWxlPSJtYXgtd2lkdGg6NjIwcHg7YmFja2dyb3VuZDojZmZmZmZmO2JvcmRlcjoxcHggc29saWQgI2UxZTdlZTtib3JkZXItcmFkaXVzOjEwcHgiPgo8dHI+PHRkIHN0eWxlPSJiYWNrZ3JvdW5kOiMxNTUyOGE7cGFkZGluZzoyNHB4O3RleHQtYWxpZ246Y2VudGVyO2NvbG9yOiNmZmZmZmY7Ym9yZGVyLXJhZGl1czoxMHB4IDEwcHggMCAwIj48aDIgc3R5bGU9Im1hcmdpbjowIj5MZW1icmV0ZSBkZSBwYWdhbWVudG88L2gyPjwvdGQ+PC90cj4KPHRyPjx0ZCBzdHlsZT0icGFkZGluZzoyOHB4Ij4KPHA+T2zDoSwgPHN0cm9uZz48Pz0gaHRtbHNwZWNpYWxjaGFycygkbm9tZSwgRU5UX1FVT1RFUywgJ1VURi04JykgPz48L3N0cm9uZz4hPC9wPgo8cD5JZGVudGlmaWNhbW9zIHF1ZSBzZXUgcGFnYW1lbnRvIHBvciA8c3Ryb25nPjw/PSBodG1sc3BlY2lhbGNoYXJzKCRmb3JtYSwgRU5UX1FVT1RFUywgJ1VURi04JykgPz48L3N0cm9uZz4gYWluZGEgY29uc3RhIGNvbW8gcGVuZGVudGUgZSB2ZW5jZSA8c3Ryb25nPjw/PSAkZGlhcyA9PT0gMSA/ICdhbWFuaMOjJyA6ICdlbSAzIGRpYXMnID8+PC9zdHJvbmc+LjwvcD4KPHRhYmxlIHJvbGU9InByZXNlbnRhdGlvbiIgd2lkdGg9IjEwMCUiIGNlbGxwYWRkaW5nPSI5IiBjZWxsc3BhY2luZz0iMCIgc3R5bGU9ImJhY2tncm91bmQ6I2Y2ZjlmYztib3JkZXI6MXB4IHNvbGlkICNlM2U4ZjA7Ym9yZGVyLXJhZGl1czo2cHgiPgo8dHI+PHRkPjxzdHJvbmc+RXZlbnRvPC9zdHJvbmc+PC90ZD48dGQ+PD89IGh0bWxzcGVjaWFsY2hhcnMoJGV2ZW50bywgRU5UX1FVT1RFUywgJ1VURi04JykgPz48L3RkPjwvdHI+Cjx0cj48dGQ+PHN0cm9uZz5Gb3JtYTwvc3Ryb25nPjwvdGQ+PHRkPjw/PSBodG1sc3BlY2lhbGNoYXJzKCRmb3JtYSwgRU5UX1FVT1RFUywgJ1VURi04JykgPz48L3RkPjwvdHI+Cjx0cj48dGQ+PHN0cm9uZz5WYWxvcjwvc3Ryb25nPjwvdGQ+PHRkPjw/PSBodG1sc3BlY2lhbGNoYXJzKCR2YWxvciwgRU5UX1FVT1RFUywgJ1VURi04JykgPz48L3RkPjwvdHI+Cjx0cj48dGQ+PHN0cm9uZz5WZW5jaW1lbnRvPC9zdHJvbmc+PC90ZD48dGQ+PD89IGh0bWxzcGVjaWFsY2hhcnMoJGRhdGEsIEVOVF9RVU9URVMsICdVVEYtOCcpID8+PC90ZD48L3RyPgo8L3RhYmxlPgo8cCBzdHlsZT0idGV4dC1hbGlnbjpjZW50ZXI7bWFyZ2luOjMwcHggMCI+PGEgaHJlZj0iPD89IGh0bWxzcGVjaWFsY2hhcnMoJHVybCwgRU5UX1FVT1RFUywgJ1VURi04JykgPz4iIHN0eWxlPSJkaXNwbGF5OmlubGluZS1ibG9jaztiYWNrZ3JvdW5kOiMwZDZlZmQ7Y29sb3I6I2ZmZjtwYWRkaW5nOjE0cHggMjRweDtib3JkZXItcmFkaXVzOjZweDt0ZXh0LWRlY29yYXRpb246bm9uZTtmb250LXdlaWdodDpib2xkIj5Db25zdWx0YXIgcGFnYW1lbnRvPC9hPjwvcD4KPD9waHAgaWYgKCRmb3JtYSA9PT0gJ1BJWCcgJiYgJHBpeENvcGlhQ29sYSAhPT0gJycpOiA/Pgo8cD48c3Ryb25nPlBJWCBDb3BpYSBlIENvbGE6PC9zdHJvbmc+PC9wPjxwIHN0eWxlPSJwYWRkaW5nOjEycHg7YmFja2dyb3VuZDojZjZmOWZjO3dvcmQtYnJlYWs6YnJlYWstYWxsO2ZvbnQtc2l6ZToxMnB4Ij48Pz0gaHRtbHNwZWNpYWxjaGFycygkcGl4Q29waWFDb2xhLCBFTlRfUVVPVEVTLCAnVVRGLTgnKSA/PjwvcD4KPD9waHAgZW5kaWY7ID8+Cjw/cGhwIGlmICgkZm9ybWEgPT09ICdCb2xldG8nICYmICRib2xldG9MaW5oYURpZ2l0YXZlbCAhPT0gJycpOiA/Pgo8cD48c3Ryb25nPkxpbmhhIGRpZ2l0w6F2ZWwgZG8gYm9sZXRvOjwvc3Ryb25nPjwvcD48cCBzdHlsZT0icGFkZGluZzoxMnB4O2JhY2tncm91bmQ6I2Y2ZjlmYzt3b3JkLWJyZWFrOmJyZWFrLWFsbCI+PD89IGh0bWxzcGVjaWFsY2hhcnMoJGJvbGV0b0xpbmhhRGlnaXRhdmVsLCBFTlRfUVVPVEVTLCAnVVRGLTgnKSA/PjwvcD4KPD9waHAgZW5kaWY7ID8+CjxwIHN0eWxlPSJmb250LXNpemU6MTRweDtjb2xvcjojNTQ2NTc3Ij5TZSB2b2PDqiBqw6EgZWZldHVvdSBvIHBhZ2FtZW50bywgZGVzY29uc2lkZXJlIGVzdGUgYXZpc28uIEEgY29uZmlybWHDp8OjbyBwb2RlIGxldmFyIGFsZ3VtIHRlbXBvLiBFc3RlIGxlbWJyZXRlIG7Do28gYWx0ZXJhIHN1YSBpbnNjcmnDp8OjbyBuZW0gY2FuY2VsYSBvIHBhZ2FtZW50by48L3A+CjxociBzdHlsZT0iYm9yZGVyOm5vbmU7Ym9yZGVyLXRvcDoxcHggc29saWQgI2U1ZTllZSI+PHAgc3R5bGU9ImZvbnQtc2l6ZToxMnB4O2NvbG9yOiM4NzkyOWYiPk1lbnNhZ2VtIGF1dG9tw6F0aWNhIGRvIFNpc3RlbWEgUmV0aXJvLiBOw6NvIHJlc3BvbmRhIGVzdGUgZS1tYWlsLjwvcD4KPC90ZD48L3RyPjwvdGFibGU+PC90ZD48L3RyPjwvdGFibGU+CjwvYm9keT48L2h0bWw+Cg==',
    'config/lembretes_pagamento.php' => 'PD9waHAKCmRlY2xhcmUoc3RyaWN0X3R5cGVzPTEpOwoKLy8gQXZpc29zIGRlIFBJWCBlIGJvbGV0byBjb20gdmVuY2ltZW50byBlbSAzIGRpYXMgZSBlbSAxIGRpYS4KLy8gRXhlY3V0YWRvcyBwZWxvIGNyb24gdW5pZmljYWRvOiBuYW8gZSBuZWNlc3NhcmlvIGNhZGFzdHJhciBub3ZvIGNyb24uCnJldHVybiBbCiAgICAnYXRpdm8nID0+IHRydWUsCiAgICAnbGltaXRlX3Bvcl9leGVjdWNhbycgPT4gOCwKICAgICdwZXJtaXRpcl9lbnZpb19sb2NhbCcgPT4gZmFsc2UsIC8vIHByb3RlY2FvIHBhcmEgWEFNUFAvV0FNUC9MYXJhZ29uCl07Cg==',
    'tools/testar_lembretes_pagamento.php' => 'PD9waHAKCmRlY2xhcmUoc3RyaWN0X3R5cGVzPTEpOwoKaWYgKFBIUF9TQVBJICE9PSAnY2xpJykgewogICAgaHR0cF9yZXNwb25zZV9jb2RlKDQwMyk7CiAgICBleGl0KCdTb21lbnRlIHRlcm1pbmFsLicpOwp9CgpyZXF1aXJlX29uY2UgZGlybmFtZShfX0RJUl9fKSAuICcvbW9kL3NlcnZpY2VzL0xlbWJyZXRlVmVuY2ltZW50b0VtYWlsU2VydmljZS5waHAnOwoKJGZ1c28gPSBuZXcgRGF0ZVRpbWVab25lKCdBbWVyaWNhL1Nhb19QYXVsbycpOwokaG9qZSA9IG5ldyBEYXRlVGltZUltbXV0YWJsZSgndG9kYXknLCAkZnVzbyk7CiR0ZXN0ZXMgPSBbCiAgICBbMywgJGhvamUtPm1vZGlmeSgnKzMgZGF5cycpLT5mb3JtYXQoJ1ktbS1kJyldLAogICAgWzEsICRob2plLT5tb2RpZnkoJysxIGRheScpLT5mb3JtYXQoJ1ktbS1kJyldLAogICAgWzAsICRob2plLT5mb3JtYXQoJ1ktbS1kJyldLAogICAgWy0xLCAkaG9qZS0+bW9kaWZ5KCctMSBkYXknKS0+Zm9ybWF0KCdZLW0tZCcpXSwKXTsKZm9yZWFjaCAoJHRlc3RlcyBhcyBbJGVzcGVyYWRvLCAkdmVuY2ltZW50b10pIHsKICAgICRyZWFsID0gTGVtYnJldGVWZW5jaW1lbnRvRW1haWxTZXJ2aWNlOjpkaWFzQXRlVmVuY2ltZW50bygkdmVuY2ltZW50bywgJGhvamUtPmZvcm1hdCgnWS1tLWQnKSk7CiAgICBpZiAoJHJlYWwgIT09ICRlc3BlcmFkbykgewogICAgICAgIGZ3cml0ZShTVERFUlIsICJbRVJST10gRGF0YSB7JHZlbmNpbWVudG99OiBlc3BlcmFkbyB7JGVzcGVyYWRvfSwgb2J0aWRvICIgLiB2YXJfZXhwb3J0KCRyZWFsLCB0cnVlKSAuICJcbiIpOwogICAgICAgIGV4aXQoMSk7CiAgICB9Cn0KZWNobyAiW09LXSBEYXRhcyBlIGFudGVjZWRlbmNpYXM6IDMgZSAxIGRpYXMgYW50ZXM7IHZlbmNpbWVudG8gZSBkYXRhIHBhc3NhZGEuXG4iOwoKaWYgKCFpbl9hcnJheSgnLS1jb25zdWx0YXInLCAkYXJndiwgdHJ1ZSkpIHsKICAgIGVjaG8gIltJTkZPXSBOZW5odW0gYmFuY28gYWNlc3NhZG8gZSBuZW5odW0gZS1tYWlsIGVudmlhZG8uXG4iOwogICAgZWNobyAiW0lORk9dIFBhcmEgY29uc3VsdGFyIGNvYnJhbmNhcyBlbGVnaXZlaXMgc2VtIGVudmlhcjogcGhwIHRvb2xzL3Rlc3Rhcl9sZW1icmV0ZXNfcGFnYW1lbnRvLnBocCAtLWNvbnN1bHRhclxuIjsKICAgIGV4aXQoMCk7Cn0KCiRjb25maWcgPSByZXF1aXJlIGRpcm5hbWUoX19ESVJfXykgLiAnL2NvbmZpZy9jb25uLnBocCc7CiRkc24gPSBzcHJpbnRmKCdteXNxbDpob3N0PSVzO2RibmFtZT0lcztjaGFyc2V0PSVzJywgJGNvbmZpZ1snaG9zdCddLCAkY29uZmlnWydkYXRhYmFzZSddLCAkY29uZmlnWydjaGFyc2V0J10gPz8gJ3V0ZjhtYjQnKTsKJGRiID0gbmV3IFBETygkZHNuLCAkY29uZmlnWyd1c2VybmFtZSddLCAkY29uZmlnWydwYXNzd29yZCddLCBbUERPOjpBVFRSX0VSUk1PREUgPT4gUERPOjpFUlJNT0RFX0VYQ0VQVElPTl0pOwokcmVzdW1vID0gKG5ldyBMZW1icmV0ZVZlbmNpbWVudG9FbWFpbFNlcnZpY2UoJGRiKSktPnNpbXVsYXIoKTsKZWNobyAnW09LXSBQcmV2aWEgKFNFTSBFTlZJQVIpOiAnIC4ganNvbl9lbmNvZGUoJHJlc3VtbywgSlNPTl9VTkVTQ0FQRURfVU5JQ09ERSB8IEpTT05fVU5FU0NBUEVEX1NMQVNIRVMpIC4gUEhQX0VPTDsK',
];
$arquivos = [];
foreach ($arquivosCodificados as $caminho => $base64) {
    $conteudo = base64_decode($base64, true);
    if ($conteudo === false || !str_starts_with($conteudo, '<?php') && !str_starts_with($conteudo, '<!doctype')) {
        fwrite(STDERR, "[ERRO] Conteudo incorporado invalido: {$caminho}.\n");
        exit(1);
    }
    $arquivos[$caminho] = $conteudo;
}

function escrever(string $mensagem): void { echo $mensagem . PHP_EOL; }
function falhar(string $mensagem): never { fwrite(STDERR, '[ERRO] ' . $mensagem . PHP_EOL); exit(1); }

function validarSintaxePhp(string $conteudo, string $caminho): void
{
    if (!str_ends_with($caminho, '.php')) { return; }
    try {
        token_get_all($conteudo, TOKEN_PARSE);
    } catch (ParseError $e) {
        falhar('Sintaxe invalida em ' . $caminho . ': ' . $e->getMessage());
    }
}

function obterPDO(string $raiz): PDO
{
    $arquivo = $raiz . '/config/conn.php';
    if (!is_file($arquivo)) { falhar('config/conn.php nao encontrado.'); }
    $cfg = require $arquivo;
    if (!is_array($cfg)) { falhar('Config de banco invalida.'); }
    $obrigatorios = ['host','database','username','password'];
    foreach ($obrigatorios as $campo) {
        if (!array_key_exists($campo, $cfg)) { falhar('Configuracao do banco incompleta.'); }
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['database'], $cfg['charset'] ?? 'utf8mb4');
    return new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function verificarColunas(PDO $db, string $tabela, array $colunas): void
{
    $existentes = [];
    foreach ($db->query('SHOW COLUMNS FROM `' . $tabela . '`')->fetchAll(PDO::FETCH_ASSOC) as $linha) {
        $existentes[(string) $linha['Field']] = true;
    }
    foreach ($colunas as $coluna) {
        if (!isset($existentes[$coluna])) {
            falhar('Campo necessario ausente: ' . $tabela . '.' . $coluna . '. Nenhuma alteracao aplicada.');
        }
    }
}

function tabelaExiste(PDO $db, string $tabela): bool
{
    $stmt = $db->query('SHOW TABLES');
    while (($nome = $stmt->fetchColumn()) !== false) {
        if ((string) $nome === $tabela) { return true; }
    }
    return false;
}

function verificarEstrutura(PDO $db): void
{
    $exigidas = [
        'pagamentos' => ['idPagamento', 'idInscricao', 'status', 'formaPagamento', 'dataVencimento', 'dataPagamento', 'valor', 'invoiceUrl', 'bankSlipUrl', 'pixCopiaCola', 'pixExpiracao', 'boletoLinhaDigitavel', 'participante', 'email'],
        'inscricoes' => ['idInscricao', 'idEvento', 'idUsuario', 'status', 'nome', 'email'],
        'eventos' => ['idEvento', 'titulo'],
        'usuarios' => ['id', 'nome', 'email'],
    ];
    foreach ($exigidas as $tabela => $campos) {
        if (!tabelaExiste($db, $tabela)) { falhar('Tabela obrigatoria ausente: ' . $tabela); }
        verificarColunas($db, $tabela, $campos);
    }
    if (tabelaExiste($db, 'email_lembretes_vencimento')) {
        verificarColunas($db, 'email_lembretes_vencimento', [
            'idLembrete','idPagamento','vencimento','diasAntes','status','tentativas','tentativaEm','enviadoEm','ultimaFalha',
        ]);
        $indices = $db->query('SHOW INDEX FROM email_lembretes_vencimento')->fetchAll(PDO::FETCH_ASSOC);
        $unicos = [];
        foreach ($indices as $item) {
            if ((int) ($item['Non_unique'] ?? 1) === 0) {
                $unicos[$item['Key_name']][(int) $item['Seq_in_index']] = (string) $item['Column_name'];
            }
        }
        $valido = false;
        foreach ($unicos as $idx) {
            ksort($idx);
            if (array_values($idx) === ['idPagamento', 'vencimento', 'diasAntes']) { $valido = true; }
        }
        if (!$valido) { falhar('Tabela de lembretes encontrada sem indice UNIQUE esperado. Nenhuma alteracao aplicada.'); }
    }
}

function criarTabela(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS email_lembretes_vencimento (
        idLembrete BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        idPagamento INT NOT NULL,
        vencimento DATE NOT NULL,
        diasAntes TINYINT UNSIGNED NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'Processando',
        tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        tentativaEm DATETIME NOT NULL,
        enviadoEm DATETIME DEFAULT NULL,
        ultimaFalha VARCHAR(500) DEFAULT NULL,
        UNIQUE KEY uq_pagamento_vencimento_antecedencia (idPagamento, vencimento, diasAntes),
        KEY idx_status_tentativa (status, tentativaEm)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function prepararCron(string $conteudo): array
{
    $marca = 'LEMBRETES_VENCIMENTO_AUTOMATICOS_V1';
    $require = "require_once dirname(__DIR__) . '/mod/services/EmailSistemaNotificacaoService.php';";
    $requireNovo = "require_once dirname(__DIR__) . '/mod/services/LembreteVencimentoEmailService.php';";
    $fim = 'exit($falhou ? 1 : 0);';

    if (str_contains($conteudo, $marca)) {
        if (!str_contains($conteudo, $requireNovo)) { falhar('Cron contem marca dos lembretes, mas falta o require. Revise manualmente.'); }
        return [$conteudo, false];
    }
    if (!str_contains($conteudo, $require) || !str_contains($conteudo, $fim)) {
        falhar('Cron atual tem estrutura diferente da esperada. Nao foi alterado.');
    }
    if (str_contains($conteudo, $requireNovo)) {
        falhar('Cron ja contem um carregamento de lembretes diferente. Revisao manual necessaria.');
    }
    $bloco = <<<'BLOCO'
// LEMBRETES_VENCIMENTO_AUTOMATICOS_V1
try {
    $lembretes = (new LembreteVencimentoEmailService($db))->processar();
    if (($lembretes['candidatos'] ?? 0) > 0 || ($lembretes['falhas'] ?? 0) > 0) {
        echo '[' . date('d/m/Y H:i:s') . '] Lembretes PIX/Boleto: '
            . json_encode($lembretes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
} catch (Throwable $erro) {
    $falhou = true;
    fwrite(STDERR, '[Retiro] Falha ao processar lembretes de vencimento: ' . $erro->getMessage() . PHP_EOL);
}

BLOCO;
    $novo = str_replace($require, $require . "\n" . $requireNovo, $conteudo);
    $novo = str_replace($fim, $bloco . $fim, $novo);
    if ($novo === $conteudo || substr_count($novo, $marca) !== 1) {
        falhar('Nao foi possivel preparar a alteracao do cron com seguranca.');
    }
    return [$novo, true];
}

function gravarSeguro(string $destino, string $conteudo): void
{
    $pasta = dirname($destino);
    if (!is_dir($pasta)) { throw new RuntimeException('Pasta ausente: ' . $pasta); }
    $temporario = tempnam($pasta, '.retiro-lemb-');
    if ($temporario === false) { throw new RuntimeException('Nao foi possivel criar arquivo temporario.'); }
    try {
        if (file_put_contents($temporario, $conteudo, LOCK_EX) !== strlen($conteudo)) {
            throw new RuntimeException('Falha ao gravar ' . basename($destino));
        }
        if (is_file($destino)) {
            $permissoes = fileperms($destino) & 0777;
            @chmod($temporario, $permissoes);
        }
        if (!@rename($temporario, $destino)) {
            if (!@copy($temporario, $destino)) {
                throw new RuntimeException('Nao foi possivel substituir ' . $destino);
            }
        }
    } finally {
        if (is_file($temporario)) { @unlink($temporario); }
    }
}

escrever('=====================================================');
escrever('RETIRO - LEMBRETES DE VENCIMENTO PIX E BOLETO');
escrever('=====================================================');
escrever('Modo: ' . ($verificar ? 'PREVIA (sem alterar)' : 'INSTALACAO'));
escrever('Pasta: ' . $raiz);
escrever('Avisos: 3 e 1 dias corridos antes do vencimento');

$necessarios = ['cron/sincronizar-notificacoes.php', 'config/conn.php', 'mod/mail/Mail.php', 'mod/services/EmailNotificacaoService.php'];
foreach ($necessarios as $arquivo) {
    if (!is_file($raiz . '/' . $arquivo)) { falhar('Arquivo do sistema nao encontrado: ' . $arquivo); }
}
$cronCaminho = $raiz . '/cron/sincronizar-notificacoes.php';
$cronOriginal = file_get_contents($cronCaminho);
if ($cronOriginal === false) { falhar('Nao foi possivel ler cron/sincronizar-notificacoes.php.'); }
[$cronNovo, $cronAlterado] = prepararCron($cronOriginal);
validarSintaxePhp($cronNovo, 'cron/sincronizar-notificacoes.php');
$novos = [];
foreach ($arquivos as $relativo => $conteudo) {
    validarSintaxePhp($conteudo, $relativo);
    $destino = $raiz . '/' . $relativo;
    if (is_file($destino)) {
        $anterior = file_get_contents($destino);
        if ($anterior !== $conteudo) {
            // Arquivo de configuracao alterado pelo usuario nao deve ser substituido.
            if ($relativo === 'config/lembretes_pagamento.php' && str_contains((string) $anterior, "'permitir_envio_local'")) {
                escrever('[PRESERVADO] Configuracao personalizada: ' . $relativo);
                continue;
            }
            falhar('Arquivo ja existe e e diferente: ' . $relativo . '. Nenhuma alteracao sera sobrescrita.');
        }
        escrever('[OK] Ja instalado: ' . $relativo);
    } else {
        $novos[$relativo] = $conteudo;
        escrever('[NOVO] ' . $relativo);
    }
}
escrever($cronAlterado ? '[ALTERAR] cron/sincronizar-notificacoes.php (insercao localizada)' : '[OK] Cron ja integrado');

try {
    $db = obterPDO($raiz);
    verificarEstrutura($db);
    $existeTabela = tabelaExiste($db, 'email_lembretes_vencimento');
    escrever($existeTabela ? '[OK] Tabela de controle de envios ja existe' : '[NOVO] Criar tabela email_lembretes_vencimento');
} catch (Throwable $e) {
    falhar('Banco nao disponivel ou incompativel: ' . $e->getMessage() . '. Nada foi modificado.');
}

if ($verificar) {
    escrever('[PREVIA] Nenhum arquivo ou dado foi alterado.');
    escrever('[PROXIMO] php aplicar_alteracao.php');
    exit(0);
}

// Evita que duas instalacoes avancem ao mesmo tempo.
$lockCaminho = sys_get_temp_dir() . '/retiro_lembretes_instalacao.lock';
$lock = fopen($lockCaminho, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { falhar('Outra instalacao esta em andamento.'); }

$backupDir = dirname($raiz) . '/backups-retiro';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    falhar('Nao foi possivel criar pasta de backup fora do site: ' . $backupDir);
}
$backup = $backupDir . '/sincronizar-notificacoes-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.php.bak';
if ($cronAlterado) {
    if (!@copy($cronCaminho, $backup)) { falhar('Falha ao criar backup do cron; instalacao interrompida.'); }
    escrever('[OK] Backup: ' . $backup);
}

$criados = [];
$cronGravado = false;
try {
    // A tabela e criada antes de ativar o cron; so adiciona tabela nova.
    if (!$existeTabela) {
        criarTabela($db);
        verificarEstrutura($db);
        escrever('[OK] Tabela de controle criada; tabelas existentes preservadas');
    }
    foreach ($novos as $relativo => $conteudo) {
        $destino = $raiz . '/' . $relativo;
        $criados[] = $destino;
        gravarSeguro($destino, $conteudo);
        if (file_get_contents($destino) !== $conteudo) { throw new RuntimeException('Arquivo gravado nao confere: ' . $relativo); }
        escrever('[OK] ' . $relativo);
    }
    if ($cronAlterado) {
        gravarSeguro($cronCaminho, $cronNovo);
        $cronGravado = true;
        if (file_get_contents($cronCaminho) !== $cronNovo) { throw new RuntimeException('Cron nao confere apos gravacao.'); }
        escrever('[OK] Cron integrado ao agendador existente');
    }
    escrever('[SUCESSO] Lembretes instalados. Nenhum pagamento ou inscricao foi alterado.');
    escrever('[TESTE] php tools/testar_lembretes_pagamento.php');
    escrever('[CONSULTA SEM ENVIAR] php tools/testar_lembretes_pagamento.php --consultar');
    escrever('[NOTA] No XAMPP os lembretes ficam bloqueados para envio por seguranca.');
} catch (Throwable $e) {
    if ($cronGravado && is_file($backup)) { @copy($backup, $cronCaminho); }
    foreach ($criados as $arquivo) { @unlink($arquivo); }
    falhar('Instalacao interrompida; arquivos restaurados: ' . $e->getMessage() . '. A tabela nova pode permanecer vazia, sem afetar o sistema.');
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
