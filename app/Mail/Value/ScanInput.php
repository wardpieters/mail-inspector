<?php
namespace App\Mail\Value;
use Illuminate\Validation\ValidationException;
readonly class ScanInput {
 public function __construct(public string $domain,public array $selectors=[]) {}
 public static function fromRaw(string $domain,array $selectors=[]): self {
  if(str_ends_with($domain,'..')||preg_match('/[\s@:\\/]/u',$domain)||filter_var($domain,FILTER_VALIDATE_IP)) throw ValidationException::withMessages(['domain'=>'Enter a domain name, without a URL or email address.']);
  $ascii=idn_to_ascii(rtrim($domain,'.'),IDNA_NONTRANSITIONAL_TO_ASCII,INTL_IDNA_VARIANT_UTS46,$info);
  if(!$ascii||($info['errors']??0)||strlen($ascii)>253||!str_contains($ascii,'.')) throw ValidationException::withMessages(['domain'=>'Enter a valid domain name.']);
  foreach(explode('.',$ascii) as $label) if(!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',$label)) throw ValidationException::withMessages(['domain'=>'The domain contains an invalid DNS label.']);
  if(count($selectors)>10) throw ValidationException::withMessages(['selectors'=>'Use at most ten DKIM selectors.']);
  $clean=[];
  foreach($selectors as $selector){if(!is_string($selector)||strlen($selector)>190) throw ValidationException::withMessages(['selectors'=>'Invalid DKIM selector.']); foreach(explode('.',$selector) as $label) if(!preg_match('/^[a-z0-9_-]{1,63}$/i',$label)) throw ValidationException::withMessages(['selectors'=>'Selectors may contain DNS labels separated by dots.']); $clean[]=strtolower($selector);}
  return new self(strtolower($ascii),array_values(array_unique($clean)));
 }
}
