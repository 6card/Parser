# Установка

    $ composer intsall
    $ sudo chmod +x parser

# Использование

    $ ./parser <config> {[command] -param=argument}

Например 

    $ ./parser currenttime
    
Доступные конфиги:

 - currenttime
 - golosameriki
 - youtube
 
Если надо добавить новый парсер, надо создать новый конфигурационный файл и новый контроллер

Имеется возможность проверить работу парсера:

    $ ./parser <config> test -items

Например для youtube, чтобы проверить парсинг всех видео с канала надо выполнить:

    $ ./parser youtube test -items

Чтобы проверить какая информация будет добавляться (для youtube информация по скачаным файлам, title будет на английском языке):

    $ ./parser youtube test -item=JByTsaJT82I


Для currenttime или golosameriki надо добавлять ссылку на страницу ролика

    $ ./parser youtube test -item=https://www.currenttime.tv/a/29011688.html