<?php

namespace Jeidison\JSignPDF\Tests;

use InvalidArgumentException;
use Jeidison\JSignPDF\Sign\JSignParam;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JSignParamTest extends TestCase
{
    #[DataProvider('providerValuesThatLookLikeAPasswordOption')]
    public function testKeepsAValueThatLooksLikeAPasswordOptionInTheParameters(string $value): void
    {
        $params = JSignParam::instance()->setJSignParameters(['--l2-text' => $value]);

        $this->assertSame([], $params->getPasswords());
        $this->assertSame(
            escapeshellarg('--l2-text') . ' ' . escapeshellarg($value),
            $params->getJSignParameters()
        );
    }

    public static function providerValuesThatLookLikeAPasswordOption(): array
    {
        return [
            'short option' => ['-tsp'],
            'long option' => ['--tsa-password'],
            'short option with assignment' => ['-tsp=some-text'],
            'long option with assignment' => ['--tsa-password=some-text'],
        ];
    }

    public function testKeepsTheFlagThatFollowsAValueLookingLikeAPasswordOption(): void
    {
        $params = JSignParam::instance()->setJSignParameters(['--l2-text' => '-tsp', '-V']);

        $this->assertSame([], $params->getPasswords());
        $this->assertSame(
            escapeshellarg('--l2-text') . ' ' . escapeshellarg('-tsp') . ' ' . escapeshellarg('-V'),
            $params->getJSignParameters()
        );
    }

    public function testRendersFlagsAndOptionsWithAValue(): void
    {
        $params = JSignParam::instance()->setJSignParameters(['-a', '-kst' => 'PKCS12']);

        $this->assertSame(
            escapeshellarg('-a') . ' ' . escapeshellarg('-kst') . ' ' . escapeshellarg('PKCS12'),
            $params->getJSignParameters()
        );
    }

    #[DataProvider('providerPasswords')]
    public function testTakesThePasswordOutOfTheParameters(array $parameters, string $expected): void
    {
        $params = JSignParam::instance()->setJSignParameters($parameters);

        $this->assertSame(['-tsp' => $expected], $params->getPasswords());
        $this->assertSame('', $params->getJSignParameters());
    }

    public static function providerPasswords(): array
    {
        $passwords = [
            'plain' => 'secret',
            'dash' => '-',
            'spaces' => 'password with spaces',
            'single quotes' => "password'with'quotes",
            'double quotes' => 'password"with"quotes',
            'assignment' => 'pass=word',
            'dashes' => 'pass-with-dashes',
            'shell metacharacters' => 'pass$with;chars',
            'backticks' => 'pass`with`backticks',
            'pipes' => 'pass&with|pipes',
            'redirections' => 'pass>with<input',
            'backslashes' => 'pass\\with\\slashes',
            'tabs' => "pass\twith\ttabs",
            'latin accents' => 'senhaçã',
            'cyrillic' => 'пароль',
            'chinese' => '密码',
            'arabic' => 'كلمةسر',
            'emoji' => '🔐secret',
            'emoji inside' => 'pass😊word',
            'zero width joiner' => '👩‍💻🔑',
        ];
        $cases = [];
        foreach ($passwords as $label => $password) {
            $cases["short option, $label"] = [['-tsp' => $password], $password];
            $cases["long option, $label"] = [['--tsa-password' => $password], $password];
            $cases["short option with assignment, $label"] = [["-tsp=$password"], $password];
            $cases["long option with assignment, $label"] = [["--tsa-password=$password"], $password];
        }
        return $cases;
    }

    #[DataProvider('providerPasswordOptions')]
    public function testRejectsAPasswordOptionGivenWithoutItsValue(string $option): void
    {
        $this->expectException(InvalidArgumentException::class);

        JSignParam::instance()->setJSignParameters([$option, '-kst' => 'PKCS12']);
    }

    public static function providerPasswordOptions(): array
    {
        return [
            'short option' => ['-tsp'],
            'long option' => ['--tsa-password'],
            'short option of another password' => ['-kp'],
            'long option of another password' => ['--owner-password'],
        ];
    }

    #[DataProvider('providerCertificatePasswordParameters')]
    public function testRejectsTheCertificatePasswordGivenInTheParameters(array $parameters): void
    {
        $this->expectException(InvalidArgumentException::class);

        JSignParam::instance()->setJSignParameters($parameters);
    }

    public static function providerCertificatePasswordParameters(): array
    {
        return [
            'short option' => [['-ksp' => 'secret']],
            'long option' => [['--keystore-password' => 'secret']],
            'short option with assignment' => [['-ksp=secret']],
            'long option with assignment' => [['--keystore-password=secret']],
            'without a value' => [['-ksp']],
        ];
    }

    #[DataProvider('providerPasswordsWithALineBreak')]
    public function testRejectsAPasswordWithALineBreakGivenToASetter(string $password): void
    {
        $this->expectException(InvalidArgumentException::class);

        JSignParam::instance()->setTsaPassword($password);
    }

    #[DataProvider('providerPasswordsWithALineBreak')]
    public function testRejectsACertificatePasswordWithALineBreak(string $password): void
    {
        $this->expectException(InvalidArgumentException::class);

        JSignParam::instance()->setPassword($password);
    }

    #[DataProvider('providerPasswordsWithALineBreak')]
    public function testRejectsAPasswordWithALineBreakGivenInTheParameters(string $password): void
    {
        $this->expectException(InvalidArgumentException::class);

        JSignParam::instance()->setJSignParameters(['-tsp' => $password]);
    }

    public static function providerPasswordsWithALineBreak(): array
    {
        return [
            'line feed' => ["pass\nword"],
            'carriage return' => ["pass\rword"],
            'both' => ["pass\r\nword"],
        ];
    }
}
