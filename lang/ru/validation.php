<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'required' => 'Поле :attribute обязательно для заполнения.',
    'string' => 'Поле :attribute должно быть строкой.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'numeric' => 'Поле :attribute должно быть числом.',
    'boolean' => 'Поле :attribute должно быть логическим значением.',
    'array' => 'Поле :attribute должно быть массивом.',
    'email' => 'Поле :attribute должно быть действительным электронным адресом.',
    'date' => 'Поле :attribute должно быть корректной датой.',
    'date_format' => 'Поле :attribute должно соответствовать формату :format.',
    'after' => 'Поле :attribute должно быть датой после :date.',
    'after_or_equal' => 'Поле :attribute должно быть датой после или равной :date.',
    'before' => 'Поле :attribute должно быть датой до :date.',
    'before_or_equal' => 'Поле :attribute должно быть датой до или равной :date.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'exists' => 'Выбранное значение поля :attribute некорректно.',
    'in' => 'Выбранное значение поля :attribute некорректно.',
    'enum' => 'Выбранное значение поля :attribute некорректно.',
    'lowercase' => 'Поле :attribute должно быть в нижнем регистре.',
    'confirmed' => 'Поле :attribute не совпадает с подтверждением.',
    'current_password' => 'Неверный пароль.',
    'image' => 'Поле :attribute должно быть изображением.',
    'mimes' => 'Поле :attribute должно быть файлом одного из типов: :values.',
    'file' => 'Поле :attribute должно быть файлом.',
    'regex' => 'Поле :attribute имеет неверный формат.',

    'between' => [
        'array' => 'Поле :attribute должно содержать от :min до :max элементов.',
        'file' => 'Размер файла :attribute должен быть от :min до :max килобайт.',
        'numeric' => 'Поле :attribute должно быть между :min и :max.',
        'string' => 'Поле :attribute должно содержать от :min до :max символов.',
    ],

    'max' => [
        'array' => 'Поле :attribute не может содержать больше :max элементов.',
        'file' => 'Размер файла :attribute не может превышать :max килобайт.',
        'numeric' => 'Поле :attribute не может быть больше :max.',
        'string' => 'Поле :attribute не может быть длиннее :max символов.',
    ],

    'min' => [
        'array' => 'Поле :attribute должно содержать не менее :min элементов.',
        'file' => 'Размер файла :attribute должен быть не менее :min килобайт.',
        'numeric' => 'Поле :attribute должно быть не менее :min.',
        'string' => 'Поле :attribute должно содержать не менее :min символов.',
    ],

    'size' => [
        'array' => 'Поле :attribute должно содержать :size элементов.',
        'file' => 'Размер файла :attribute должен быть :size килобайт.',
        'numeric' => 'Поле :attribute должно быть равно :size.',
        'string' => 'Поле :attribute должно содержать :size символов.',
    ],

    'password' => [
        'letters' => 'Поле :attribute должно содержать хотя бы одну букву.',
        'mixed' => 'Поле :attribute должно содержать хотя бы одну заглавную и одну строчную букву.',
        'numbers' => 'Поле :attribute должно содержать хотя бы одну цифру.',
        'symbols' => 'Поле :attribute должно содержать хотя бы один символ.',
        'uncompromised' => 'Значение :attribute встречалось в утечках данных. Выберите другое значение.',
    ],

    'custom' => [],

    'attributes' => [
        'name' => 'имя',
        'surname' => 'фамилия',
        'first_name' => 'имя',
        'last_name' => 'фамилия',
        'email' => 'email',
        'password' => 'пароль',
        'password_confirmation' => 'подтверждение пароля',
        'birth_date' => 'дата рождения',
        'current_rating' => 'рейтинг',
        'title' => 'разряд / титул',
        'notes' => 'примечания',
        'tournament_id' => 'турнир',
        'club_id' => 'клуб',
        'manager_id' => 'менеджер клуба',
        'user_id' => 'пользователь',
        'code' => 'код клуба',
        'city' => 'город',
        'address' => 'адрес',
        'website' => 'веб-сайт',
        'description' => 'описание',
        'logo' => 'логотип',
        'remove_logo' => 'удаление логотипа',
        'player_id' => 'шахматист',
        'arbiter_id' => 'судья',
        'start_date' => 'дата начала',
        'end_date' => 'дата окончания',
        'location' => 'место проведения',
        'rounds_count' => 'количество туров',
        'games_per_round' => 'партий в туре',
        'pairing_type' => 'тип жеребьёвки',
        'rating_mode' => 'режим расчёта рейтинга',
        'role' => 'роль',
        'reason' => 'причина',
        'version' => 'версия',
        'file' => 'файл',
        'result' => 'результат',
        'advanced_player_id' => 'проходящий участник',
        'search' => 'поиск',
        'status' => 'статус',
    ],

];
