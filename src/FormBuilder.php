<?php

namespace NetoJose\Bootstrap4Forms;

use Illuminate\Support\ViewErrorBag;

class FormBuilder
{

    private $_attrs = [];

    public function set($key, $value)
    {
        $formatter = 'format'.ucfirst($key);
        if (method_exists($this, $formatter)) {
            $value = $this->$formatter($value);
        }
        $this->attrs[$key] = $value;
    }

    private function formatStyle($style)
    {
        return implode(';', array_map(function ($v) {
            return trim($v, ';');
        }, (array)$style)) ?: null;
    }

    private function formatMethod($value)
    {
        return strtolower($value);
    }

    private function formatFormData($value)
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }
        return $value;
    }

    private function formatOptions($value)
    {
        extract($this->get('optionIdKey', 'optionValueKey'));

        $idKey = $optionIdKey ?? 'id';
        $valueKey = $optionValueKey ?? 'name';

        $options = [];
        foreach ($value as $key => $item) {
            if (is_object($item)) {
                $options[$item->{$idKey}] = $item->{$valueKey};
                continue;
            }
            $options[$key] = $item;
        }
        return $options;
    }

    public function render(): string
    {
        $render = $this->attrs['render'];
        $methodName = 'render'.ucfirst($render);
        $output = $this->$methodName();
        $this->resetAttributes();
        return $output;
    }

    private function renderFormOpen(): string
    {
        extract($this->get('id', 'method', 'url', 'formMultipart', 'formInline', 'autocomplete', 'style'));

        if (!$method) {
            $method = 'post';
        }

        $enctype = $formMultipart ? 'multipart/form-data' : null;

        $attrs = $this->buildHtmlAttrs([
            'method'       => in_array($method, ['get', 'post']) ? $method : 'post',
            'action'       => $url,
            'enctype'      => $enctype,
            'autocomplete' => $autocomplete,
            'class'        => $formInline ? 'form-inline' : null,
            'style'        => $this->formatStyle($style),
            'id'           => $id,
        ]);

        $output = '<form '.$attrs.'>';

        if ($method !== 'get') {
            $output .= csrf_field();
            if ($method !== 'post') {
                $output .= method_field($method);
            }
        }

        return $output;
    }

    private function renderFormClose(): string
    {
        $this->resetAttributes(true);
        return '</form>';
    }

    private function renderFieldsetOpen(): string
    {
        $output = '<fieldset>';
        extract($this->get('legend'));

        if ($legend) {
            $output .= '<legend>'.$this->getText($legend).'</legend>';
        }

        return $output;
    }

    private function renderFieldsetClose(): string
    {
        return '</fieldset>';
    }

    private function renderErrors(): string
    {
        $errors = $this->errors()->all();
        if (count($errors) < 1) {
            return '';
        }

        extract($this->get('errorsHeader', 'id', 'wrapperStyle'));
        $attrs = $this->buildHtmlAttrs([
            'class' => 'alert alert-danger',
            'style' => $this->formatStyle($wrapperStyle),
            'role'  => 'alert',
            'id'    => $id,
        ]);
        $output = '<div '.$attrs.'><ul class="list-unstyled">';
        if ($errorsHeader) {
            $output .= '<h4 class="alert-heading">'.$this->getText($errorsHeader).'</h4>';
        }
        foreach ($errors as $error) {
            $output .= '<li>'.$error.'</li>';
        }
        return $output.'</ul></div>';
    }

    private function renderInput(): string
    {
        $attributes = $this->getInputAttributes();
        $attrs = $this->buildHtmlAttrs($attributes);
        return $this->wrapperInput('<input '.$attrs.'>');
    }

    private function renderSelect(): string
    {
        extract($this->get('options'));

        $fieldValue = $this->getValue();
        $arrValues = is_array($fieldValue) ? $fieldValue : [$fieldValue];
        $optionsList = '';
        foreach ($options as $value => $label) {
            $attrs = $this->buildHtmlAttrs(['value' => $value, 'selected' => in_array($value, $arrValues)], false);
            $optionsList .= '<option '.$attrs.'>'.$label.'</option>';
        }

        $attributes = $this->getInputAttributes();
        $attrs = $this->buildHtmlAttrs($attributes);
        return $this->wrapperInput('<select '.$attrs.'>'.$optionsList.'</select>');
    }

    private function renderTextarea(): string
    {
        $attributes = $this->getInputAttributes();
        $value = $attributes['value'];
        unset($attributes['value']);
        $attrs = $this->buildHtmlAttrs($attributes);
        return $this->wrapperInput('<textarea '.$attrs.'>'.htmlspecialchars($value).'</textarea>');
    }

    private function renderCheckbox(): string
    {
        $attributes = $this->getInputAttributes();
        $attrs = $this->buildHtmlAttrs($attributes);
        return $this->wrapperRadioCheckbox('<input '.$attrs.'>', $attributes['type']);
    }

    private function renderRadio(): string
    {
        $attributes = $this->getInputAttributes();
        $attrs = $this->buildHtmlAttrs($attributes);
        return $this->wrapperRadioCheckbox('<input '.$attrs.'>');
    }

    private function renderAnchor(): string
    {
        extract($this->get('url', 'value', 'style', 'title'));
        $class = $this->getBtnAnchorClasses();
        $attrs = $this->buildHtmlAttrs(['href' => $url, 'class' => $class, 'style' => $this->formatStyle($style), 'title' => $title]);

        return '<a '.$attrs.'>'.$value.'</a>';
    }

    private function renderButton(): string
    {
        extract($this->get('type', 'value', 'disabled', 'class', 'style', 'title', 'id', 'name'));
        $class = implode(' ', array_merge((array)$this->getBtnAnchorClasses(), (array)$class));
        $attrs = $this->buildHtmlAttrs(['type' => $type, 'class' => $class, 'id' => $id, 'style' => $this->formatStyle($style), 'disabled' => $disabled, 'title' => $title, 'name' => $name]);

        return '<button '.$attrs.'>'.$value.'</button>';
    }

    private function getBtnAnchorClasses()
    {
        extract($this->get('size', 'color', 'outline', 'block', 'type', 'value', 'formInline'));
        return $this->createAttrsList(
            'btn',
            [$size, 'btn-'.$size],
            [$color, 'btn-'.($outline ? 'outline-' : '').$color],
            [$block, 'btn-block'],
            [$formInline, 'mx-sm-2']
        );
    }

    private function isRadioOrCheckbox(): bool
    {
        extract($this->get('render'));
        return in_array($render, ['checkbox', 'radio']);
    }

    private function getInputAttributes(): array
    {
        extract($this->get('render', 'type', 'multiple', 'name', 'size', 'placeholder', 'title', 'class', 'style', 'help', 'disabled', 'readonly', 'required', 'autocomplete', 'min', 'max', 'value', 'checked', 'formData', 'disableValidation'));

        $isRadioOrCheckbox = $this->isRadioOrCheckbox();
        $type = $isRadioOrCheckbox ? $render : $type;

        $setClass = (array)$class;
        $class = 'form-check-input';
        if (!$isRadioOrCheckbox) {
            $class = 'form-control';
            switch ($type) {
                case 'file':
                    $class .= '-file';
                    break;
                case 'range':
                    $class .= '-range';
                    break;
            }

            if ($size) {
                $class .= ' form-control-'.$size;
            }
        }
        if ($setClass) {
            $class .= ' '.implode(' ', $setClass);
        }

        $id = $this->getId();

        if (!$disableValidation && $this->errors()->count() > 0) {
            $class .= $this->errors()->has($name) ? ' is-invalid' : ' is-valid';
        }

        $attributes = [
            'type'     => $type,
            'name'     => $this->getName(),
            'id'       => $id,
            'disabled' => $disabled,
            'class'    => $class,
        ];

        if ($render !== 'select') {
            $attributes['value'] = $this->getValue();
        } else {
            $attributes['multiple'] = $multiple;
        }

        // If the field is a hidden field, we don't need add more attributes
        if ($type === 'hidden') {
            return $attributes;
        }

        if ($this->isRadioOrCheckbox()) {
            if ($this->hasOldInput()) {
                $isChecked = old($name) == $value;
            } else {
                $isChecked = isset($formData[$name]) ? $formData[$name] == $value : $checked;
            }
            $attributes['checked'] = $isChecked;
        }

        return array_merge($attributes, [
            'style'            => $this->formatStyle($style),
            'min'              => $min,
            'max'              => $max,
            'autocomplete'     => $autocomplete,
            'placeholder'      => $this->getText($placeholder),
            'title'            => $title,
            'aria-describedby' => $help ? 'help-'.$id : null,
            'readonly'         => $readonly,
            'required'         => $required
        ]);
    }

    private function renderLabel(): string
    {
        extract($this->get('label', 'formInline', 'render', 'labelClass', 'style', 'for', 'noLabel'));

        if ($noLabel) {
            return '';
        }

        $class = in_array($render, ['checkbox', 'radio']) ? ['form-check-label'] : [];
        if ($formInline) {
            $class[] = 'mx-sm-2';
        }
        $class = implode(' ', array_merge($class, (array)$labelClass));

        $id = $this->getId();
        $attrs = $this->buildHtmlAttrs([
            'for'   => $for ?: $id,
            'class' => $class,
            'style' => $this->formatStyle($style),
        ], false);
        return '<label '.$attrs.'>'.$this->getText($label).'</label>';
    }

    private function getText($key)
    {
        extract($this->get('formLocale'));
        if ($formLocale) {
            return __($formLocale.'.'.$key);
        }
        return $key;
    }

    private function resetAttributes($resetAll = false)
    {
        // Remove all attributes
        if ($resetAll) {
            $this->attrs = [];
            return;
        }

        // Keep attributes which key starting with 'form'
        $this->attrs = array_filter($this->attrs, function ($key) {
            return substr($key, 0, 4) === 'form';
        }, ARRAY_FILTER_USE_KEY);
    }

    private function wrapperInput(string $input): string
    {
        extract($this->get('type', 'help', 'wrapperAttrs', 'wrapperClass', 'wrapperStyle', 'noWrapper', 'groupPrepend', 'groupAppend', 'formInline', 'name'));

        if ($type === 'hidden') {
            return $input;
        }

        $id = $this->getId();
        $label = $this->renderLabel();
        $helpText = $help ? '<small id="help-'.$id.'" class="form-text text-muted">'.$this->getText($help).'</small>' : '';
        $error = $this->getInputErrorMarkup($name);

        $prepend = $groupPrepend ? sprintf('<div class="input-group-prepend"><span class="input-group-text">%s</span></div>', $groupPrepend) : '';
        $append = $groupAppend ? sprintf('<div class="input-group-append"><span class="input-group-text">%s</span></div>', $groupAppend) : '';
        if ($prepend || $append) {
            $input = '<div class="input-group">'.$prepend.$input.$append.'</div>';
        }

        $html = $label.$input.$helpText.$error;

        if ($noWrapper) {
            return $html;
        }

        $attrs = $wrapperAttrs ?? [];
        $attrs['class'] = $this->createAttrsList(
            $attrs['class'] ?? null,
            $formInline ? 'input-group' : 'form-group',
            ...(array)$wrapperClass
        );
        $attrs['style'] = $this->formatStyle($wrapperStyle);
        $attributes = $this->buildHtmlAttrs($attrs, false);

        return '<div '.$attributes.'>'.$html.'</div>';
    }

    private function wrapperRadioCheckbox(string $input, string $type = null): string
    {
        extract($this->get('inline', 'name', 'wrapperAttrs', 'wrapperClass', 'wrapperStyle', 'noWrapper', 'noHiddenCheckbox'));

        $label = $this->renderLabel();
        $error = $this->getInputErrorMarkup($name);
        $hidden = $type == 'checkbox' && !$noHiddenCheckbox ? '<input type="hidden" name="'.$this->getName($name).'" value="0" />' : '';

        $html = $hidden.$input.$label.$error;

        if ($noWrapper) {
            return $html;
        }

        $attrs = $wrapperAttrs ?? [];
        $attrs['class'] = $this->createAttrsList(
            'form-check',
            [$inline, 'form-check-inline'],
            $attrs['class'] ?? null,
            ...(array)$wrapperClass
        );
        $attrs['style'] = $this->formatStyle($wrapperStyle);
        $attributes = $this->buildHtmlAttrs($attrs, false);

        return '<div '.$attributes.'>'.$html.'</div>';
    }

    private function getInputErrorMarkup(string $name): string
    {
        extract($this->get('disableValidation'));

        if ($disableValidation) {
            return '';
        }

        $error = $this->errors()->first($name);
        if (!$error) {
            return '';
        }
        return '<div class="invalid-feedback">'.$this->errors()->first($name).'</div>';
    }

    private function getId()
    {
        extract($this->get('id', 'name', 'formIdPrefix', 'render', 'value', 'noId'));

        if ($noId) {
            return null;
        }

        if ($id) {
            return $id;
        }

        return ($formIdPrefix ?? 'inp-').str_replace('.', '-', trim($name, '.-*')).($render === 'radio' ? '-'.$value : '');
    }

    private function getName()
    {
        extract($this->get('name', 'isArray'));

        if (($pos = strpos($name, '.')) !== false) {
            $inputName = substr_replace($name, '[', $pos, 1);
            $inputName = str_replace('.', '][', $inputName).']';
            $name = $inputName;
        }

        return $name.($isArray ? '[]' : '');
    }

    private function hasOldInput()
    {
        return count((array)old()) != 0;
    }

    private function getValue()
    {
        extract($this->get('name', 'value', 'formData'));
        if ($this->isRadioOrCheckbox()) {
            return $value;
        }

        if ($this->hasOldInput()) {
            return old($name, $value);
        }

        $fromFill = $formData[$name] ?? null;

        return $value ?? $fromFill;
    }

    private function buildHtmlAttrs(array $attributes, $appendAttrs = true): string
    {
        if ($appendAttrs) {
            extract($this->get('attrs'));
            $fieldAttrs = $attrs ?? [];
            $class = $this->createAttrsList($attributes['class'] ?? null, $fieldAttrs['class'] ?? null);
            if ($class) {
                $attributes['class'] = $class;
            }
            $attributes = array_merge($fieldAttrs, $attributes);
        }

        return join(' ', array_filter(
            array_map(function ($key) use ($attributes) {
                $value = $attributes[$key];
                if (is_bool($value)) {
                    return $value ? $key : '';
                } elseif ($value !== null) {
                    return $key.'="'.htmlspecialchars($value).'"';
                }
                return '';
            }, array_keys($attributes))
        ));
    }

    private function createAttrsList(...$items)
    {
        $attrs = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = $item[0] ? $item[1] : null;
            }
            $attrs[] = $item;
        }
        return join(' ', array_filter($attrs));
    }

    private function errors()
    {
        $errors = session('errors', app(ViewErrorBag::class));
        extract($this->get('formErrorBag'));
        if ($formErrorBag) {
            $errors = $errors->{$formErrorBag};
        }
        return $errors;
    }

    private function get(...$keys): array
    {
        $return = [];
        foreach ($keys as $key) {
            $return[$key] = $this->attrs[$key] ?? null;
        }
        return $return;
    }
}
