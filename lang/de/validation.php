<?php

return [

    'after_or_equal' => ':Attribute muss ein Datum nach oder gleich :date sein.',
    'array' => ':Attribute muss eine Liste sein.',
    'before' => ':Attribute muss ein Datum vor :date sein.',
    'between' => [
        'numeric' => ':Attribute muss zwischen :min und :max liegen.',
        'string' => ':Attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':Attribute muss wahr oder falsch sein.',
    'date_format' => ':Attribute muss dem Format :format entsprechen.',
    'distinct' => ':Attribute enthält einen doppelten Wert.',
    'email' => ':Attribute muss eine gültige E-Mail-Adresse sein.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'gt' => [
        'numeric' => ':Attribute muss größer als :value sein.',
    ],
    'image' => ':Attribute muss ein Bild sein.',
    'integer' => ':Attribute muss eine ganze Zahl sein.',
    'max' => [
        'file' => ':Attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':Attribute darf höchstens :max sein.',
        'string' => ':Attribute darf höchstens :max Zeichen lang sein.',
    ],
    'min' => [
        'array' => ':Attribute muss mindestens :min Einträge haben.',
        'string' => ':Attribute muss mindestens :min Zeichen lang sein.',
    ],
    'not_in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'numeric' => ':Attribute muss eine Zahl sein.',
    'required' => ':Attribute muss ausgefüllt werden.',
    'required_without' => ':Attribute muss ausgefüllt werden, wenn :values fehlt.',
    'string' => ':Attribute muss ein Text sein.',
    'unique' => ':Attribute ist bereits vergeben.',
    'url' => ':Attribute muss eine gültige Adresse sein.',
    'password' => [
        'letters' => ':Attribute muss mindestens einen Buchstaben enthalten.',
    ],

    'attributes' => [
        'amount' => 'Menge',
        'amount_in_base' => 'Menge',
        'date' => 'Tag',
        'from' => 'Beginn',
        'label' => 'Bezeichnung',
        'meal' => 'Mahlzeit',
        'meals' => 'Mahlzeiten',
        'name' => 'Name',
        'password' => 'Passwort',
        'servings' => 'Portionen',
        'title' => 'Titel',
        'to' => 'Ende',
        'url' => 'Link',
        'username' => 'Benutzername',
    ],

];
