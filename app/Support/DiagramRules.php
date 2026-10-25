<?php

namespace App\Support;

final class DiagramRules
{
    public static function diagram(string $prefix): array
    {
        $rules = [
            $prefix => ['nullable', 'array:title,elements,image_data,width,align'],
            "$prefix.title" => ['nullable', 'string', 'max:150'],
            "$prefix.image_data" => ['nullable', 'string', 'max:7000000', 'regex:/^data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+\/=\s]+$/'],
            "$prefix.width" => ['nullable', 'numeric', 'min:60', 'max:500'],
            "$prefix.align" => ['nullable', 'in:left,center'],
            "$prefix.elements" => ['nullable', 'array', 'max:300'],
            "$prefix.elements.*" => ['array:kind,x,y,width,height,x2,y2,rx,ry,points,text,stroke,fill,strokeWidth,fontSize,rotation,dashed'],
            "$prefix.elements.*.kind" => ['required', 'in:rect,ellipse,line,arrow,polygon,polyline,text'],
            "$prefix.elements.*.text" => ['nullable', 'string', 'max:500'],
            "$prefix.elements.*.points" => ['nullable', 'string', 'max:8000', 'regex:/^[0-9\s,.\-]+$/'],
            "$prefix.elements.*.stroke" => ['nullable', 'regex:/^(#[a-fA-F0-9]{6}|none)$/'],
            "$prefix.elements.*.fill" => ['nullable', 'regex:/^(#[a-fA-F0-9]{6}|none)$/'],
            "$prefix.elements.*.dashed" => ['sometimes', 'boolean'],
        ];
        foreach (['x', 'y', 'width', 'height', 'x2', 'y2', 'rx', 'ry', 'strokeWidth', 'fontSize', 'rotation'] as $key) {
            $rules["$prefix.elements.*.$key"] = ['sometimes', 'numeric', 'min:-1000', 'max:2000'];
        }

        return $rules;
    }

}
