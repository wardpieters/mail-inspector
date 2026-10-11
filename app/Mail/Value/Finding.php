<?php
namespace App\Mail\Value;
use App\Mail\Enums\{Outcome,Severity};
readonly class Finding implements \JsonSerializable {
 public function __construct(public string $key,public string $scope,public Outcome $outcome,public Severity $severity,public string $title,public string $explanation,public ?string $recommendation,public array $evidence,public int $durationMs=0){}
 public static function make(string $key,string $outcome,string $title,string $explanation='',array $evidence=[],?string $recommendation=null,string $severity='none',string $scope='domain'): self {return new self($key,$scope,Outcome::from($outcome),Severity::from($severity),$title,$explanation,$recommendation,['observed_at'=>gmdate('c')]+$evidence);}
 public function jsonSerialize(): array {return ['key'=>$this->key,'scope'=>$this->scope,'outcome'=>$this->outcome->value,'severity'=>$this->severity->value,'title'=>$this->title,'explanation'=>$this->explanation,'recommendation'=>$this->recommendation,'evidence'=>$this->evidence,'duration_ms'=>$this->durationMs];}
}
