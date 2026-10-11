<?php

namespace App\Mail\Network;

use App\Mail\Value\EndpointTarget;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ProbePermits
{
    public function acquire(string $scanId, EndpointTarget $target): ?string
    {
        $token = (string) Str::uuid();
        $keys = ['mail:permit:scan:'.$scanId, 'mail:permit:mx:'.hash('sha256', $target->mx), 'mail:permit:ip:'.hash('sha256', app(TargetGuard::class)->normalize($target->ip))];
        $script = <<<'LUA'
local t=redis.call('TIME'); local now=tonumber(t[1])+tonumber(t[2])/1000000
local limits={4,2,2}
for i=1,3 do redis.call('ZREMRANGEBYSCORE',KEYS[i],'-inf',now); if redis.call('ZCARD',KEYS[i])>=limits[i] then return 0 end end
for i=1,3 do redis.call('ZADD',KEYS[i],now+90,ARGV[1]); redis.call('EXPIRE',KEYS[i],100) end
redis.call('SET',KEYS[4],cjson.encode({KEYS[1],KEYS[2],KEYS[3]}),'EX',90)
return 1
LUA;

        return Redis::eval($script, 4, ...[...$keys, 'mail:permit:owner:'.$token, $token]) ? $token : null;
    }

    public function release(string $token): void
    {
        $script = <<<'LUA'
local value=redis.call('GET',KEYS[1]); if not value then return 0 end
for _,key in ipairs(cjson.decode(value)) do redis.call('ZREM',key,ARGV[1]) end
redis.call('DEL',KEYS[1]); return 1
LUA;
        Redis::eval($script, 1, 'mail:permit:owner:'.$token, $token);
    }
}
