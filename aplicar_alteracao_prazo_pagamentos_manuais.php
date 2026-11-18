<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(403); exit; }
$raiz = __DIR__;
$patches = [
    'mod/services/EmailNotificacaoService.php' => [
        ['RETIRO_SEM_VENCIDO_MANUAL_DIRETO_V1', 'ICAgICAgICAkdWx0aW1vU3RhdHVzID0gdHJpbSgKICAgICAgICAgICAgKHN0cmluZykgKAogICAgICAgICAgICAgICAgJHBhZ2FtZW50b1siZW1haWxfc3RhdHVzX25vdGlmaWNhZG8iXQ==', 'ICAgICAgICAvLyBSRVRJUk9fU0VNX1ZFTkNJRE9fTUFOVUFMX0RJUkVUT19WMQogICAgICAgIC8vIFByYXpvIGFkbWluaXN0cmF0aXZvIG7Do28gw6kgdmVuY2ltZW50byBkbyBwYWdhbWVudG8gZG8gcGFydGljaXBhbnRlLgogICAgICAgIGlmICgkc3RhdHVzID09PSAiVmVuY2lkbyIgJiYgaW5fYXJyYXkoKHN0cmluZykgKCRwYWdhbWVudG9bImZvcm1hUGFnYW1lbnRvIl0gPz8gIiIpLCBbIkRpbmhlaXJvIiwgIlRyYW5zZmVyZW5jaWEiXSwgdHJ1ZSkpIHsKICAgICAgICAgICAgcmV0dXJuIHRydWU7IC8vIEF2aXNvIHN1cHJpbWlkbyBkZWxpYmVyYWRhbWVudGU7IG5lbmh1bSBlLW1haWwgZW52aWFkby4KICAgICAgICB9CgogICAgICAgICR1bHRpbW9TdGF0dXMgPSB0cmltKAogICAgICAgICAgICAoc3RyaW5nKSAoCiAgICAgICAgICAgICAgICAkcGFnYW1lbnRvWyJlbWFpbF9zdGF0dXNfbm90aWZpY2FkbyJd'],
        ['RETIRO_SEM_VENCIDO_MANUAL_LOTE_V1', 'ICAgICAgICAgICAgICBBTkQgcC5zdGF0dXMgSU4gKHskc3RhdHVzU3FsfSkKICAgICAgICAgICAgICBBTkQgKA==', 'ICAgICAgICAgICAgICBBTkQgcC5zdGF0dXMgSU4gKHskc3RhdHVzU3FsfSkKICAgICAgICAgICAgICAvKiBSRVRJUk9fU0VNX1ZFTkNJRE9fTUFOVUFMX0xPVEVfVjEgKi8KICAgICAgICAgICAgICBBTkQgTk9UIChwLnN0YXR1cyA9ICdWZW5jaWRvJyBBTkQgcC5mb3JtYVBhZ2FtZW50byBJTiAoJ0RpbmhlaXJvJywgJ1RyYW5zZmVyZW5jaWEnKSkKICAgICAgICAgICAgICBBTkQgKA=='],
    ],
    'cron/sincronizar-notificacoes.php' => [
        ['RETIRO_PRAZO_MANUAL_ADMIN_V1', 'ZXhpdCgkZmFsaG91ID8gMSA6IDApOw==', 'Ly8gUkVUSVJPX1BSQVpPX01BTlVBTF9BRE1JTl9WMQp0cnkgewogICAgcmVxdWlyZV9vbmNlIGRpcm5hbWUoX19ESVJfXykgLiAnL21vZC9zZXJ2aWNlcy9QYWdhbWVudG9zTWFudWFpc1ByYXpvU2VydmljZS5waHAnOwogICAgJHByYXpvTWFudWFsID0gKG5ldyBQYWdhbWVudG9zTWFudWFpc1ByYXpvU2VydmljZSgkZGIpKS0+cHJvY2Vzc2FyKCk7CiAgICBpZiAoKCRwcmF6b01hbnVhbFsnZW52aWFkb3MnXSA/PyAwKSA+IDAgfHwgKCRwcmF6b01hbnVhbFsnZmFsaGFzJ10gPz8gMCkgPiAwKSB7CiAgICAgICAgZWNobyAnWycgLiBkYXRlKCdkL20vWSBIOmk6cycpIC4gJ10gUHJhem8gZGUgcmVnaXN0cm8gbWFudWFsOiAnCiAgICAgICAgICAgIC4ganNvbl9lbmNvZGUoJHByYXpvTWFudWFsLCBKU09OX1VORVNDQVBFRF9VTklDT0RFKSAuIFBIUF9FT0w7CiAgICB9CiAgICBpZiAoKCRwcmF6b01hbnVhbFsnZmFsaGFzJ10gPz8gMCkgPiAwKSB7ICRmYWxob3UgPSB0cnVlOyB9Cn0gY2F0Y2ggKFRocm93YWJsZSAkZXJybykgewogICAgJGZhbGhvdSA9IHRydWU7CiAgICBmd3JpdGUoU1RERVJSLCAnW1JldGlyb10gRmFsaGEgbm8gYXZpc28gZGUgcHJhem8gbWFudWFsOiAnIC4gJGVycm8tPmdldE1lc3NhZ2UoKSAuIFBIUF9FT0wpOwp9CmV4aXQoJGZhbGhvdSA/IDEgOiAwKTs='],
    ],
];
$novos = [
    'mod/services/PagamentosManuaisPrazoService.php' => base64_decode("PD9waHAKZGVjbGFyZShzdHJpY3RfdHlwZXM9MSk7CgovKiogUHJhem8gZGUgcmVnaXN0cm8gZmluYW5jZWlybywgbnVuY2EgcHJhem8gZGUgY29icmFuw6dhIGRvIHBhcnRpY2lwYW50ZS4gKi8KZmluYWwgY2xhc3MgUGFnYW1lbnRvc01hbnVhaXNQcmF6b1NlcnZpY2UKewogICAgcHJpdmF0ZSBjb25zdCBMT0NLID0gJ3JldGlyb19wcmF6b19tYW51YWxfYWRtaW5fdjEnOwogICAgcHJpdmF0ZSBjb25zdCBUQUJFTEEgPSAnZW1haWxfcHJhem9fbWFudWFsX2FkbWluJzsKICAgIHByaXZhdGUgY29uc3QgRlVTTyA9ICdBbWVyaWNhL1Nhb19QYXVsbyc7CiAgICBwdWJsaWMgZnVuY3Rpb24gX19jb25zdHJ1Y3QocHJpdmF0ZSBQRE8gJGRiKSB7fQoKICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gZGF0YShtaXhlZCAkdik6ID9EYXRlVGltZUltbXV0YWJsZQogICAgewogICAgICAgICRzID0gdHJpbSgoc3RyaW5nKSR2KTsKICAgICAgICBpZiAoIXByZWdfbWF0Y2goJy9eXGR7NH0tXGR7Mn0tXGR7Mn0oPzokfFsgVF1cZHsyfTpcZHsyfTpcZHsyfSQpL0QnLCAkcykpIHJldHVybiBudWxsOwogICAgICAgICRzID0gc3Vic3RyKCRzLDAsMTApOwogICAgICAgICRkID0gRGF0ZVRpbWVJbW11dGFibGU6OmNyZWF0ZUZyb21Gb3JtYXQoJyFZLW0tZCcsICRzLCBuZXcgRGF0ZVRpbWVab25lKHNlbGY6OkZVU08pKTsKICAgICAgICByZXR1cm4gJGQgJiYgJGQtPmZvcm1hdCgnWS1tLWQnKSA9PT0gJHMgJiYgc3Vic3RyKCRzLDAsNCkgIT09ICcwMDAwJyA/ICRkIDogbnVsbDsKICAgIH0KICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gcHJhem8oYXJyYXkgJGxpbmhhKTogP0RhdGVUaW1lSW1tdXRhYmxlCiAgICB7CiAgICAgICAgJGluaWNpbyA9IHNlbGY6OmRhdGEoJGxpbmhhWydkYXRhX2luaWNpbyddID8/IG51bGwpOwogICAgICAgICRmaW0gPSBzZWxmOjpkYXRhKCRsaW5oYVsnZGF0YV9maW0nXSA/PyBudWxsKTsKICAgICAgICBpZiAoISRpbmljaW8pIHJldHVybiBudWxsOwogICAgICAgIGlmICghJGZpbSkgJGZpbSA9ICRpbmljaW87CiAgICAgICAgaWYgKCRmaW0gPCAkaW5pY2lvKSByZXR1cm4gbnVsbDsKICAgICAgICByZXR1cm4gJGZpbS0+bW9kaWZ5KCcrMiBkYXlzJyk7CiAgICB9CiAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGNsYXNzaWZpY2FyKGFycmF5ICRsaW5oYSwgc3RyaW5nICRob2plKTogP2FycmF5CiAgICB7CiAgICAgICAgJHByYXpvID0gc2VsZjo6cHJhem8oJGxpbmhhKTsgJGRpYSA9IHNlbGY6OmRhdGEoJGhvamUpOwogICAgICAgIGlmICghJHByYXpvIHx8ICEkZGlhIHx8ICRkaWEgPD0gJHByYXpvLT5tb2RpZnkoJy0yIGRheXMnKSkgcmV0dXJuIG51bGw7CiAgICAgICAgJGxpbmhhWydwcmF6b19yZWdpc3RybyddID0gJHByYXpvLT5mb3JtYXQoJ1ktbS1kJyk7CiAgICAgICAgJGxpbmhhWydzaXR1YWNhb19yZWdpc3RybyddID0gJGRpYSA+ICRwcmF6byA/ICdQcmF6byBkZSByZWdpc3RybyB1bHRyYXBhc3NhZG8nIDogKCRkaWEgPT0gJHByYXpvID8gJ8OabHRpbW8gZGlhIHBhcmEgcmVnaXN0cmFyJyA6ICdBIHJlZ2lzdHJhcicpOwogICAgICAgICRsaW5oYVsnY29uZmxpdG9fYXNhYXMnXSA9ICgkbGluaGFbJ2ludGVncmFjYW8nXSA/PyAnJykgPT09ICdBc2FhcycgfHwgdHJpbSgoc3RyaW5nKSgkbGluaGFbJ2FzYWFzUGF5bWVudElkJ10gPz8gJycpKSAhPT0gJyc7CiAgICAgICAgcmV0dXJuICRsaW5oYTsKICAgIH0KICAgIHB1YmxpYyBmdW5jdGlvbiBjb25zdWx0YXIoP3N0cmluZyAkaG9qZSA9IG51bGwpOiBhcnJheQogICAgewogICAgICAgICRkaWEgPSAkaG9qZSA/PyAobmV3IERhdGVUaW1lSW1tdXRhYmxlKCd0b2RheScsIG5ldyBEYXRlVGltZVpvbmUoc2VsZjo6RlVTTykpKS0+Zm9ybWF0KCdZLW0tZCcpOwogICAgICAgIGlmICghc2VsZjo6ZGF0YSgkZGlhKSkgdGhyb3cgbmV3IEludmFsaWRBcmd1bWVudEV4Y2VwdGlvbignRGF0YSBpbnbDoWxpZGEuJyk7CiAgICAgICAgLy8gTsOjbyBsaW1pdGEgYSBldmVudG9zIGF0aXZvczogdW0gZXZlbnRvIGVuY2VycmFkbyBwb2RlIHRlciBzaWRvIGRlc2F0aXZhZG8uCiAgICAgICAgJHNxbCA9ICJTRUxFQ1QgaS5pZEluc2NyaWNhbywgcC5pZFBhZ2FtZW50bywgZS50aXR1bG8gQVMgZXZlbnRvLCBlLmRhdGFfaW5pY2lvLCBlLmRhdGFfZmltLAogICAgICAgICAgICAgICAgICAgIENPQUxFU0NFKE5VTExJRihUUklNKGkubm9tZSksJycpLCBOVUxMSUYoVFJJTShwLnBhcnRpY2lwYW50ZSksJycpLCAnUGFydGljaXBhbnRlJykgQVMgbm9tZSwKICAgICAgICAgICAgICAgICAgICBwLmZvcm1hUGFnYW1lbnRvIEFTIGZvcm1hLCBwLnZhbG9yLCBwLmludGVncmFjYW8sIHAuYXNhYXNQYXltZW50SWQKICAgICAgICAgICAgICAgIEZST00gaW5zY3JpY29lcyBpIElOTkVSIEpPSU4gZXZlbnRvcyBlIE9OIGUuaWRFdmVudG89aS5pZEV2ZW50bwogICAgICAgICAgICAgICAgSU5ORVIgSk9JTiBwYWdhbWVudG9zIHAgT04gcC5pZFBhZ2FtZW50bz0oU0VMRUNUIE1BWChwMi5pZFBhZ2FtZW50bykgRlJPTSBwYWdhbWVudG9zIHAyIFdIRVJFIHAyLmlkSW5zY3JpY2FvPWkuaWRJbnNjcmljYW8pCiAgICAgICAgICAgICAgICBXSEVSRSBpLnN0YXR1cyA8PiAnQ2FuY2VsYWRhJyBBTkQgQ09BTEVTQ0UoaS5wYWdhbWVudG8sJycpIDw+ICdQYWdvJwogICAgICAgICAgICAgICAgICBBTkQgcC5mb3JtYVBhZ2FtZW50byBJTiAoJ0RpbmhlaXJvJywnVHJhbnNmZXJlbmNpYScpCiAgICAgICAgICAgICAgICAgIEFORCBwLnN0YXR1cyBJTiAoJ1BlbmRlbnRlJywnVmVuY2lkbycpIEFORCBwLmRhdGFQYWdhbWVudG8gSVMgTlVMTCBBTkQgcC52YWxvciA+IDAKICAgICAgICAgICAgICAgICAgQU5EIGUuZGF0YV9pbmljaW8gPCA6aG9qZQogICAgICAgICAgICAgICAgT1JERVIgQlkgZS5kYXRhX2luaWNpbyBBU0MsZS50aXR1bG8gQVNDLGkubm9tZSBBU0MsaS5pZEluc2NyaWNhbyBBU0MiOwogICAgICAgICRzdG10ID0gJHRoaXMtPmRiLT5wcmVwYXJlKCRzcWwpOyAkc3RtdC0+ZXhlY3V0ZShbJzpob2plJz0+JGRpYV0pOwogICAgICAgICRyZXN1bHRhZG89W107CiAgICAgICAgZm9yZWFjaCAoJHN0bXQtPmZldGNoQWxsKFBETzo6RkVUQ0hfQVNTT0MpIGFzICRsaW5oYSkgewogICAgICAgICAgICAkdj1zZWxmOjpjbGFzc2lmaWNhcigkbGluaGEsJGRpYSk7IGlmICgkdiE9PW51bGwpICRyZXN1bHRhZG9bXT0kdjsKICAgICAgICB9CiAgICAgICAgcmV0dXJuICRyZXN1bHRhZG87CiAgICB9CiAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHJlbmRlcml6YXIoYXJyYXkgJGxpbmhhcywgc3RyaW5nICRkaWEpOiBzdHJpbmcKICAgIHsKICAgICAgICAkZXNjPXN0YXRpYyBmbihtaXhlZCAkdik6IHN0cmluZz0+aHRtbHNwZWNpYWxjaGFycygoc3RyaW5nKSR2LEVOVF9RVU9URVN8RU5UX1NVQlNUSVRVVEUsJ1VURi04Jyk7CiAgICAgICAgJGRhdGE9c3RhdGljIGZuKG1peGVkICR2KTogc3RyaW5nPT5zZWxmOjpkYXRhKCR2KT8tPmZvcm1hdCgnZC9tL1knKSA/PyAn4oCUJzsKICAgICAgICAkbW9lZGE9c3RhdGljIGZuKGZsb2F0ICR2KTogc3RyaW5nPT4nUiQgJy5udW1iZXJfZm9ybWF0KCR2LDIsJywnLCcuJyk7CiAgICAgICAgJHRvdGFsPTAuMDsgJGF0cmFzYWRvcz0wOyAkdHJzPScnOwogICAgICAgIGZvcmVhY2goJGxpbmhhcyBhcyAkbCl7CiAgICAgICAgICAgICR0b3RhbCs9KGZsb2F0KSRsWyd2YWxvciddOyAkYXRyYXNhZG9zKz0oJGxbJ3ByYXpvX3JlZ2lzdHJvJ108JGRpYSk/MTowOwogICAgICAgICAgICAkZm9ybWE9JGxbJ2Zvcm1hJ109PT0nVHJhbnNmZXJlbmNpYSc/J1RyYW5zZmVyw6puY2lhJzonRGluaGVpcm8nOwogICAgICAgICAgICAkb2JzPSRsWydjb25mbGl0b19hc2FhcyddPycg4oCUIHbDrW5jdWxvIEFzYWFzOiBjb25mZXJpciBhbnRlcyBkZSBtYXJjYXInOicnOwogICAgICAgICAgICAkdHJzLj0nPHRyPjx0ZD4jJy4oaW50KSRsWydpZEluc2NyaWNhbyddLic8L3RkPjx0ZD4nLiRlc2MoJGxbJ25vbWUnXSkuJzwvdGQ+PHRkPicuJGVzYygkbFsnZXZlbnRvJ10pLic8L3RkPjx0ZD4nLiRlc2MoJGZvcm1hKS4nPC90ZD48dGQ+Jy4kZXNjKCRtb2VkYSgoZmxvYXQpJGxbJ3ZhbG9yJ10pKS4nPC90ZD48dGQ+Jy4kZXNjKCRkYXRhKCRsWydwcmF6b19yZWdpc3RybyddKSkuJzwvdGQ+PHRkPicuJGVzYygkbFsnc2l0dWFjYW9fcmVnaXN0cm8nXS4kb2JzKS4nPC90ZD48L3RyPic7CiAgICAgICAgfQogICAgICAgIHJldHVybiAnPCFkb2N0eXBlIGh0bWw+PGh0bWwgbGFuZz0icHQtQlIiPjxtZXRhIGNoYXJzZXQ9IlVURi04Ij48Ym9keSBzdHlsZT0iZm9udC1mYW1pbHk6QXJpYWwsc2Fucy1zZXJpZjtjb2xvcjojMjQzNjRhO3BhZGRpbmc6MjBweCI+PGgyIHN0eWxlPSJjb2xvcjojMTc0YjgyIj5SZWdpc3RybyBkZSBwYWdhbWVudG9zIG1hbnVhaXM8L2gyPjxwPlJldGlybyDCtyAnLiRlc2MoJGRhdGEoJGRpYSkpLic8L3A+PHA+Q29uZmlyYSBvcyByZWNlYmltZW50b3MgZW0gZGluaGVpcm8gZSB0cmFuc2ZlcsOqbmNpYSBkb3MgZXZlbnRvcyBlbmNlcnJhZG9zLiBPIHByYXpvIGFkbWluaXN0cmF0aXZvIMOpIGF0w6kgbyBzZWd1bmRvIGRpYSBjb3JyaWRvIGFww7NzIG8gdMOpcm1pbm8gZG8gZXZlbnRvLjwvcD48cD48c3Ryb25nPlBlbmTDqm5jaWFzOiAnLmNvdW50KCRsaW5oYXMpLicgfCBBcMOzcyBvIHByYXpvOiAnLiRhdHJhc2Fkb3MuJyB8IFRvdGFsIGEgY29uZmVyaXI6ICcuJGVzYygkbW9lZGEoJHRvdGFsKSkuJzwvc3Ryb25nPjwvcD48dGFibGUgYm9yZGVyPSIxIiBjZWxscGFkZGluZz0iNyIgY2VsbHNwYWNpbmc9IjAiIHN0eWxlPSJib3JkZXItY29sbGFwc2U6Y29sbGFwc2U7Zm9udC1zaXplOjEycHgiPjx0aGVhZCBzdHlsZT0iYmFja2dyb3VuZDojZTllZmY3Ij48dHI+PHRoPkluc2NyacOnw6NvPC90aD48dGg+UGFydGljaXBhbnRlPC90aD48dGg+RXZlbnRvPC90aD48dGg+Rm9ybWE8L3RoPjx0aD5WYWxvcjwvdGg+PHRoPlByYXpvIGRlIHJlZ2lzdHJvPC90aD48dGg+U2l0dWHDp8OjbzwvdGg+PC90cj48L3RoZWFkPjx0Ym9keT4nLiR0cnMuJzwvdGJvZHk+PC90YWJsZT48cD5NYXJxdWUgY29tbyBQYWdvIGFwZW5hcyB2YWxvcmVzIGVmZXRpdmFtZW50ZSByZWNlYmlkb3MuIFJlZ2lzdHJvcyBjb20gdsOtbmN1bG8gQXNhYXMgcHJlY2lzYW0gZGUgY29uZmVyw6puY2lhLiBFc3RhIG5vdGlmaWNhw6fDo28gbsOjbyBjYW5jZWxhIGluc2NyacOnw7VlcyBlIG7Do28gY29uc3RpdHVpIGNvYnJhbsOnYSBhbyBwYXJ0aWNpcGFudGUuPC9wPjxwPjxhIGhyZWY9Imh0dHBzOi8vcmV0aXJvLmllY2xicGFyb2JlLmNvbS5ici9hZG1pbi9maW5hbmNlaXJvL3BhZ2FtZW50b3MucGhwIj5BYnJpciBmaW5hbmNlaXJvIGRvIFJldGlybzwvYT48L3A+PHAgc3R5bGU9ImZvbnQtc2l6ZToxMXB4Ij5NZW5zYWdlbSBhZG1pbmlzdHJhdGl2YSBjb25maWRlbmNpYWwuPC9wPjwvYm9keT48L2h0bWw+JzsKICAgIH0KICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gaW5zdGFsYXIoUERPICRkYik6IHZvaWQKICAgIHsKICAgICAgICAkZGItPmV4ZWMoIkNSRUFURSBUQUJMRSBJRiBOT1QgRVhJU1RTIGVtYWlsX3ByYXpvX21hbnVhbF9hZG1pbiAoZGF0YVJlZmVyZW5jaWEgREFURSBOT1QgTlVMTCxkZXN0aW5hdGFyaW9IYXNoIENIQVIoNjQpIENIQVJBQ1RFUiBTRVQgYXNjaWkgTk9UIE5VTEwsdGVudGF0aXZhcyBTTUFMTElOVCBVTlNJR05FRCBOT1QgTlVMTCBERUZBVUxUIDAsdGVudGF0aXZhRW0gREFURVRJTUUgTk9UIE5VTEwsZW52aWFkb0VtIERBVEVUSU1FIE5VTEwsUFJJTUFSWSBLRVkoZGF0YVJlZmVyZW5jaWEsZGVzdGluYXRhcmlvSGFzaCkpIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQiKTsKICAgICAgICAkZGItPnF1ZXJ5KCdTRUxFQ1QgZGF0YVJlZmVyZW5jaWEsZGVzdGluYXRhcmlvSGFzaCx0ZW50YXRpdmFzLHRlbnRhdGl2YUVtLGVudmlhZG9FbSBGUk9NIGVtYWlsX3ByYXpvX21hbnVhbF9hZG1pbiBMSU1JVCAwJyk7CiAgICB9CiAgICBwcml2YXRlIGZ1bmN0aW9uIGNvbmZpZyhzdHJpbmcgJG5vbWUpOiBhcnJheQogICAgewogICAgICAgICRwPWRpcm5hbWUoX19ESVJfXywyKS4nL2NvbmZpZy8nLiRub21lLicucGhwJzsKICAgICAgICAkY2ZnPWlzX2ZpbGUoJHApP3JlcXVpcmUgJHA6W107IHJldHVybiBpc19hcnJheSgkY2ZnKT8kY2ZnOltdOwogICAgfQogICAgcHVibGljIGZ1bmN0aW9uIHByb2Nlc3NhcigpOiBhcnJheQogICAgewogICAgICAgICRyPVsnZW52aWFkb3MnPT4wLCdmYWxoYXMnPT4wXTsKICAgICAgICAvLyBNZXNtbyBhbWJpZW50ZSBlIGRlc3RpbmF0w6FyaW9zIGRvIHJlc3VtbyBhZG1pbmlzdHJhdGl2byBleGlzdGVudGUuCiAgICAgICAgcmVxdWlyZV9vbmNlIF9fRElSX18uJy9SZXN1bW9QZW5kZW5jaWFzQWRtaW5TZXJ2aWNlLnBocCc7CiAgICAgICAgaWYgKFJlc3Vtb1BlbmRlbmNpYXNBZG1pblNlcnZpY2U6OmFtYmllbnRlTG9jYWwoZGlybmFtZShfX0RJUl9fLDIpLFBIUF9PU19GQU1JTFkpKSByZXR1cm4gJHIrWydtb3Rpdm8nPT4nQW1iaWVudGUgbG9jYWwnXTsKICAgICAgICAkYWRtaW49JHRoaXMtPmNvbmZpZygnbm90aWZpY2Fjb2VzX3Npc3RlbWEnKTsKICAgICAgICBpZiAoZW1wdHkoJGFkbWluWydhdGl2byddKSkgcmV0dXJuICRyK1snbW90aXZvJz0+J0FsZXJ0YXMgYWRtaW5pc3RyYXRpdm9zIGRlc2F0aXZhZG9zJ107CiAgICAgICAgaWYgKChpbnQpJHRoaXMtPmRiLT5xdWVyeSgiU0VMRUNUIEdFVF9MT0NLKCciLnNlbGY6OkxPQ0suIicsMCkiKS0+ZmV0Y2hDb2x1bW4oKSE9PTEpIHJldHVybiAkcjsKICAgICAgICB0cnkgewogICAgICAgICAgICAkZGlhPShuZXcgRGF0ZVRpbWVJbW11dGFibGUoJ3RvZGF5JyxuZXcgRGF0ZVRpbWVab25lKHNlbGY6OkZVU08pKSktPmZvcm1hdCgnWS1tLWQnKTsKICAgICAgICAgICAgaWYgKCR0aGlzLT5jb25zdWx0YXIoJGRpYSk9PT1bXSkgcmV0dXJuICRyOwogICAgICAgICAgICAkZW1haWxzPVJlc3Vtb1BlbmRlbmNpYXNBZG1pblNlcnZpY2U6OmRlc3RpbmF0YXJpb3MoJHRoaXMtPmNvbmZpZygnbWFpbCcpLCRhZG1pbik7CiAgICAgICAgICAgIGZvcmVhY2goJGVtYWlscyBhcyAkZW1haWwpewogICAgICAgICAgICAgICAgJGNoYXZlPVsnOmRpYSc9PiRkaWEsJzpoYXNoJz0+aGFzaCgnc2hhMjU2JyxzdHJ0b2xvd2VyKCRlbWFpbCkpXTsKICAgICAgICAgICAgICAgICRzdG10PSR0aGlzLT5kYi0+cHJlcGFyZSgnU0VMRUNUIGVudmlhZG9FbSx0ZW50YXRpdmFzLCh0ZW50YXRpdmFFbSA8PSBEQVRFX1NVQihOT1coKSwgSU5URVJWQUwgMTUgTUlOVVRFKSkgQVMgcG9kZVRlbnRhciBGUk9NIGVtYWlsX3ByYXpvX21hbnVhbF9hZG1pbiBXSEVSRSBkYXRhUmVmZXJlbmNpYT06ZGlhIEFORCBkZXN0aW5hdGFyaW9IYXNoPTpoYXNoJyk7CiAgICAgICAgICAgICAgICAkc3RtdC0+ZXhlY3V0ZSgkY2hhdmUpOyAkZXN0YWRvPSRzdG10LT5mZXRjaChQRE86OkZFVENIX0FTU09DKTsKICAgICAgICAgICAgICAgIGlmICgkZXN0YWRvICYmICghZW1wdHkoJGVzdGFkb1snZW52aWFkb0VtJ10pIHx8IChpbnQpJGVzdGFkb1sndGVudGF0aXZhcyddPj01IHx8IChpbnQpJGVzdGFkb1sncG9kZVRlbnRhciddIT09MSkpIGNvbnRpbnVlOwogICAgICAgICAgICAgICAgJGxpbmhhcz0kdGhpcy0+Y29uc3VsdGFyKCRkaWEpOyBpZiAoJGxpbmhhcz09PVtdKSBicmVhazsKICAgICAgICAgICAgICAgICRzdG10PSR0aGlzLT5kYi0+cHJlcGFyZSgnSU5TRVJUIElOVE8gZW1haWxfcHJhem9fbWFudWFsX2FkbWluKGRhdGFSZWZlcmVuY2lhLGRlc3RpbmF0YXJpb0hhc2gsdGVudGF0aXZhcyx0ZW50YXRpdmFFbSkgVkFMVUVTKDpkaWEsOmhhc2gsMSxOT1coKSkgT04gRFVQTElDQVRFIEtFWSBVUERBVEUgdGVudGF0aXZhcz10ZW50YXRpdmFzKzEsdGVudGF0aXZhRW09Tk9XKCknKTsKICAgICAgICAgICAgICAgICRzdG10LT5leGVjdXRlKCRjaGF2ZSk7CiAgICAgICAgICAgICAgICB0cnkgewogICAgICAgICAgICAgICAgICAgIHJlcXVpcmVfb25jZSBkaXJuYW1lKF9fRElSX18pLicvbWFpbC9NYWlsLnBocCc7CiAgICAgICAgICAgICAgICAgICAgJG5vbWU9dHJpbSgoc3RyaW5nKSgkYWRtaW5bJ25vbWVfZGVzdGluYXRhcmlvJ10/PydBZG1pbmlzdHJhw6fDo28gZG8gUmV0aXJvJykpID86ICdBZG1pbmlzdHJhw6fDo28gZG8gUmV0aXJvJzsKICAgICAgICAgICAgICAgICAgICBpZiAoIShuZXcgTWFpbCgpKS0+c2VuZCgkZW1haWwsJG5vbWUsJ1tSZXRpcm9dIFJlZ2lzdHJhciBwYWdhbWVudG9zIG1hbnVhaXMgLSAnLnNlbGY6OmRhdGEoJGRpYSktPmZvcm1hdCgnZC9tL1knKSxzZWxmOjpyZW5kZXJpemFyKCRsaW5oYXMsJGRpYSkpKSB0aHJvdyBuZXcgUnVudGltZUV4Y2VwdGlvbignU01UUCBuw6NvIGNvbmZpcm1vdSBvIGVudmlvLicpOwogICAgICAgICAgICAgICAgICAgICRzdG10PSR0aGlzLT5kYi0+cHJlcGFyZSgnVVBEQVRFIGVtYWlsX3ByYXpvX21hbnVhbF9hZG1pbiBTRVQgZW52aWFkb0VtPU5PVygpIFdIRVJFIGRhdGFSZWZlcmVuY2lhPTpkaWEgQU5EIGRlc3RpbmF0YXJpb0hhc2g9Omhhc2gnKTsgJHN0bXQtPmV4ZWN1dGUoJGNoYXZlKTsgJHJbJ2VudmlhZG9zJ10rKzsKICAgICAgICAgICAgICAgIH0gY2F0Y2goVGhyb3dhYmxlICRlKSB7ICRyWydmYWxoYXMnXSsrOyBlcnJvcl9sb2coJ1tSZXRpcm9dIEZhbGhhIG5vIGF2aXNvIGFkbWluaXN0cmF0aXZvIGRlIHBhZ2FtZW50b3MgbWFudWFpcy4nKTsgfQogICAgICAgICAgICB9CiAgICAgICAgICAgIHJldHVybiAkcjsKICAgICAgICB9IGZpbmFsbHkgeyAkdGhpcy0+ZGItPnF1ZXJ5KCJTRUxFQ1QgUkVMRUFTRV9MT0NLKCciLnNlbGY6OkxPQ0suIicpIik7IH0KICAgIH0KfQo="),
    'tools/consultar_pagamentos_manuais.php' => base64_decode("PD9waHAKZGVjbGFyZShzdHJpY3RfdHlwZXM9MSk7CmlmKFBIUF9TQVBJIT09J2NsaScpe2h0dHBfcmVzcG9uc2VfY29kZSg0MDMpO2V4aXQ7fQokcmFpej1kaXJuYW1lKF9fRElSX18pOwp0cnkgewogICAgcmVxdWlyZV9vbmNlICRyYWl6LicvbW9kL3NlcnZpY2VzL1BhZ2FtZW50b3NNYW51YWlzUHJhem9TZXJ2aWNlLnBocCc7CiAgICAkY2ZnPXJlcXVpcmUgJHJhaXouJy9jb25maWcvY29ubi5waHAnOwogICAgJGRiPW5ldyBQRE8oJ215c3FsOmhvc3Q9Jy4kY2ZnWydob3N0J10uJztkYm5hbWU9Jy4kY2ZnWydkYXRhYmFzZSddLic7Y2hhcnNldD0nLigkY2ZnWydjaGFyc2V0J10/Pyd1dGY4bWI0JyksJGNmZ1sndXNlcm5hbWUnXSwkY2ZnWydwYXNzd29yZCddLFtQRE86OkFUVFJfRVJSTU9ERT0+UERPOjpFUlJNT0RFX0VYQ0VQVElPTixQRE86OkFUVFJfRU1VTEFURV9QUkVQQVJFUz0+ZmFsc2VdKTsKICAgICRsaW5oYXM9KG5ldyBQYWdhbWVudG9zTWFudWFpc1ByYXpvU2VydmljZSgkZGIpKS0+Y29uc3VsdGFyKCk7CiAgICBmb3JlYWNoKCRsaW5oYXMgYXMgJGwpIGVjaG8gJyMnLiRsWydpZEluc2NyaWNhbyddLicgfCAnLiRsWydldmVudG8nXS4nIHwgJy4kbFsnZm9ybWEnXS4nIHwgUiQgJy5udW1iZXJfZm9ybWF0KChmbG9hdCkkbFsndmFsb3InXSwyLCcsJywnLicpLicgfCBQcmF6bzogJy4kbFsncHJhem9fcmVnaXN0cm8nXS4nIHwgJy4kbFsnc2l0dWFjYW9fcmVnaXN0cm8nXS4oJGxbJ2NvbmZsaXRvX2FzYWFzJ10/JyB8IENvbmZlcmlyIHbDrW5jdWxvIEFzYWFzJzonJykuUEhQX0VPTDsKICAgIGVjaG8gJ1BlbmTDqm5jaWFzIGRlIHJlZ2lzdHJvOiAnLmNvdW50KCRsaW5oYXMpLlBIUF9FT0wuJ0NvbnN1bHRhIHNvbWVudGUgbGVpdHVyYTsgbmVuaHVtIGUtbWFpbCBlbnZpYWRvLicuUEhQX0VPTDsKfSBjYXRjaChUaHJvd2FibGUgJGUpe2Z3cml0ZShTVERFUlIsIltFUlJPXSBOw6NvIGZvaSBwb3Nzw612ZWwgY29uc3VsdGFyLiBDb25maXJhIGEgY29uZXjDo28gZSBhcyBtaWdyYcOnw7Vlcy5cbiIpO2V4aXQoMSk7fQo="),
];
function falha(string $mensagem): never { throw new RuntimeException($mensagem); }
function ler(string $p): string { $s = file_get_contents($p); if ($s === false) falha('Falha ao ler: '.$p); return $s; }
function normalizar(string $s): string { return str_replace("\r\n", "\n", $s); }
function gravar(string $p, string $s): void { if (file_put_contents($p,$s,LOCK_EX) !== strlen($s)) falha('Falha ao gravar arquivo: '.$p); }
function lint(string $p): void {
    if (!function_exists('exec')) falha('exec() indisponível; não foi possível verificar sintaxe.');
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($p).' 2>&1', $out, $cod);
    if($cod !== 0) falha('Sintaxe inválida: '.implode("\n",$out));
}
$arg = $argv[1] ?? '';
if (count($argv)>2 || !in_array($arg,['','--verificar'],true)) { echo "Uso: php aplicar_alteracao_prazo_pagamentos_manuais.php [--verificar]\n"; exit(1); }
$previa = $arg === '--verificar';
$alteracoes = []; $staged=[]; $aplicados=[]; $backup='';
try {
    if (PHP_VERSION_ID < 80200) falha('Use PHP 8.2 ou superior.');
    foreach (['mod/services/ResumoPendenciasAdminService.php','config/notificacoes_sistema.php','config/conn.php'] as $dep) {
        if (!is_file($raiz.'/'.$dep)) falha('Dependência ausente: '.$dep);
    }
    $resumo = ler($raiz.'/mod/services/ResumoPendenciasAdminService.php');
    if (!str_contains($resumo, 'function destinatarios(') || !str_contains($resumo, 'function ambienteLocal(')) falha('Resumo administrativo incompatível.');

    foreach($patches as $rel => $edits) {
        $p = $raiz.'/'.$rel;
        if(!is_file($p)||is_link($p)) falha('Arquivo obrigatório ausente ou atalho: '.$rel);
        $original=ler($p); $s=normalizar($original);
        foreach($edits as [$marker,$old64,$new64]) {
            $old=base64_decode($old64,true); $new=base64_decode($new64,true);
            if(str_contains($s,$marker)) {
                if(substr_count($s,$new)!==1) falha('Alteração existente divergente: '.$rel.' / '.$marker);
                continue;
            }
            if(substr_count($s,$old)!==1) falha('Versão incompatível ou alteração concorrente em '.$rel.' / '.$marker.'. Nada será aplicado.');
            $s=str_replace($old,$new,$s);
        }
        if (str_contains($original,"\r\n")) $s=str_replace("\n","\r\n",$s);
        if($s!==$original) $alteracoes[$rel]=[$original,$s];
    }
    foreach($novos as $rel=>$novo) {
        $p=$raiz.'/'.$rel;
        if(is_link($p)||is_dir($p)) falha('Caminho de destino inválido: '.$rel);
        if(is_file($p)) { if(normalizar(ler($p))!==normalizar($novo)) falha('Arquivo auxiliar já existe com conteúdo diferente: '.$rel); }
        else { if(!is_dir(dirname($p))) falha('Diretório de destino ausente: '.dirname($rel)); $alteracoes[$rel]=[null,$novo]; }
    }
    if(!$alteracoes) { echo "[OK] Atualização já instalada.\n"; exit(0); }
    foreach($alteracoes as $rel=>[$original,$novo]) {
        $temp=tempnam(sys_get_temp_dir(),'retiro-lint-'); if($temp===false) falha('Não foi possível criar arquivo temporário.');
        try { gravar($temp,$novo); lint($temp); } finally { @unlink($temp); }
        echo "[OK] Compatível e sintaxe validada: $rel\n";
    }
    if($previa) { echo "[OK] Prévia concluída. Nenhum arquivo do sistema, banco ou Asaas alterado.\n"; exit(0); }
    $base = PHP_OS_FAMILY === 'Windows' ? (getenv('USERPROFILE') ?: '') : dirname($raiz);
    if(!$base) falha('Pasta privada de backup indisponível.');
    $pasta=$base.'/backups-retiro';
    if(!is_dir($pasta)&&!mkdir($pasta,0700,true)) falha('Não foi possível criar pasta de backup.');
    if(is_link($pasta)) falha('Pasta de backup inválida.');
    $backup=$pasta.'/prazo-manual-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
    if(!mkdir($backup,0700)) falha('Não foi possível criar backup.');
    // Arquivos auxiliares devem existir antes das classes que os utilizam.
    uksort($alteracoes, static function($a,$b) use($novos){return (isset($novos[$b])?1:0)<=>(isset($novos[$a])?1:0);});
    foreach($alteracoes as $rel=>[$original,$novo]) {
        $p=$raiz.'/'.$rel;
        if($original!==null) {
            if(ler($p)!==$original) falha('O arquivo mudou durante a instalação: '.$rel);
            $dest=$backup.'/'.$rel; if(!is_dir(dirname($dest))&&!mkdir(dirname($dest),0700,true)) falha('Falha ao preparar backup.');
            gravar($dest,$original); @chmod($dest,0600);
        } elseif(file_exists($p)) falha('O destino foi criado durante a instalação: '.$rel);
        $tmp=tempnam(dirname($p),'.retiro-prazo-'); if($tmp===false) falha('Falha ao preparar instalação.');
        $staged[$rel]=$tmp; gravar($tmp,$novo); lint($tmp);
        @chmod($tmp,$original!==null ? (fileperms($p)&0777) : 0644);
    }
    // A única alteração de banco é a tabela dedicada ao controle de e-mails.
    $cfg = require $raiz.'/config/conn.php';
    if (!is_array($cfg) || !isset($cfg['host'],$cfg['database'],$cfg['username'],$cfg['password'])) falha('Configuração do banco incompatível.');
    try {
        $db = new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['database'].';charset='.($cfg['charset']??'utf8mb4'), $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $servicoRel = 'mod/services/PagamentosManuaisPrazoService.php';
        require_once ($staged[$servicoRel] ?? $raiz.'/'.$servicoRel);
        (new PagamentosManuaisPrazoService($db))->consultar();
        PagamentosManuaisPrazoService::instalar($db);
        echo "[OK] Consulta e tabela de controle de avisos verificadas.\n";
    } catch (Throwable $e) { falha('Não foi possível preparar o banco. Confira conexão, colunas e permissão CREATE. Nenhum pagamento foi alterado.'); }
    foreach($alteracoes as $rel=>[$original,$novo]) {
        $p=$raiz.'/'.$rel;
        if(($original!==null&&ler($p)!==$original)||($original===null&&file_exists($p))) falha('Conflito durante instalação: '.$rel);
        if(!rename($staged[$rel],$p)) falha('Não foi possível aplicar: '.$rel);
        unset($staged[$rel]); $aplicados[]=$rel; echo "[OK] Instalado: $rel\n";
    }
    echo "[OK] Atualização concluída. Nenhum pagamento, inscrição ou cobrança foi modificado. Apenas tabela de controle de avisos adicionada.\nBackup: $backup\n";
} catch(Throwable $e) {
    foreach(array_reverse($aplicados) as $rel) {
        $p=$raiz.'/'.$rel; [$original,$novo]=$alteracoes[$rel];
        if($original===null) { if(!@unlink($p)) fwrite(STDERR,"Revisar manualmente: $rel\n"); }
        else { if(!@copy($backup.'/'.$rel,$p)) fwrite(STDERR,"Restaurar manualmente: $rel\n"); }
    }
    foreach($staged as $tmp) @unlink($tmp);
    fwrite(STDERR,"[ERRO] ".$e->getMessage()."\n".($backup!==''?"Backup: $backup\n":'')); exit(1);
}

