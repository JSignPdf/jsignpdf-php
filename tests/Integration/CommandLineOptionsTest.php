<?php

namespace Jeidison\JSignPDF\Tests\Integration;

use InvalidArgumentException;
use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
class CommandLineOptionsTest extends TestCase
{
    private const CERTIFICATE_PASSWORD_OPTION = '-ksp';

    private const PASSWORD_OPTIONS = ['-kp', '-opwd', '-upwd', '-tscp', '-tsp'];

    /** @var list<array{short: ?string, long: ?string, takesValue: bool}>|null */
    private static ?array $options = null;

    /**
     * @return list<array{short: ?string, long: ?string, takesValue: bool}>
     */
    private static function optionsDeclaredByJSignPdf(): array
    {
        if (self::$options !== null) {
            return self::$options;
        }

        $params = JSignParam::instance();
        JSignPDF::instance($params)->getVersion();

        $command = sprintf(
            '%s -Duser.language=en -classpath %s com.intoolswetrust.jsignpdf.Bootstrap -h 2>&1',
            escapeshellarg($params->getJavaPath()),
            escapeshellarg($params->getJSignPdfPath() . '/lib/*')
        );
        exec($command, $help);

        $options = [];
        foreach ($help as $line) {
            if (preg_match('/^ (-[A-Za-z0-9]+)(?:,(--[a-z0-9-]+))?( <[^>]+>)?(?:\s|$)/', $line, $match) === 1) {
                $options[] = ['short' => $match[1], 'long' => $match[2] ?: null, 'takesValue' => ($match[3] ?? '') !== ''];
                continue;
            }
            if (preg_match('/^    (--[a-z0-9-]+)( <[^>]+>)?(?:\s|$)/', $line, $match) === 1) {
                $options[] = ['short' => null, 'long' => $match[1], 'takesValue' => ($match[2] ?? '') !== ''];
            }
        }

        return self::$options = $options;
    }

    public function testParsesTheOptionsFromTheHelpOfJSignPdf(): void
    {
        $options = self::optionsDeclaredByJSignPdf();

        $this->assertContains(['short' => '-kst', 'long' => '--keystore-type', 'takesValue' => true], $options);
        $this->assertContains(['short' => null, 'long' => '--l2-text', 'takesValue' => true], $options);
        $this->assertContains(['short' => '-llx', 'long' => null, 'takesValue' => true], $options);
        $this->assertContains(['short' => '-a', 'long' => '--append', 'takesValue' => false], $options);
        $this->assertContains(['short' => null, 'long' => '--overwrite', 'takesValue' => false], $options);
    }

    public function testEveryOptionWithAValueReachesJSignPdfAsTheValueOfTheOption(): void
    {
        $mismatches = [];
        foreach (self::optionsDeclaredByJSignPdf() as $option) {
            if (!$option['takesValue']) {
                continue;
            }
            foreach (array_filter([$option['short'], $option['long']]) as $key) {
                $rendered = $this->render($option, $key);
                $expected = $this->expectedRendering($option);
                if ($rendered !== $expected) {
                    $mismatches[$key] = ['expected' => $expected, 'rendered' => $rendered];
                }
            }
        }

        $this->assertSame([], $mismatches);
    }

    public function testOnlyTheCoordinatesHaveNoLongName(): void
    {
        $withoutLongName = array_values(array_map(
            static fn (array $option): ?string => $option['short'],
            array_filter(
                self::optionsDeclaredByJSignPdf(),
                static fn (array $option): bool => $option['takesValue'] && $option['long'] === null
            )
        ));

        $this->assertSame(['-llx', '-lly', '-urx', '-ury'], $withoutLongName);
    }

    public function testEveryFlagIsPassedAsItIs(): void
    {
        $flags = [];
        foreach (self::optionsDeclaredByJSignPdf() as $option) {
            if (!$option['takesValue']) {
                array_push($flags, ...array_filter([$option['short'], $option['long']]));
            }
        }

        $params = JSignParam::instance()->setJSignParameters($flags);

        $this->assertSame(implode(' ', array_map('escapeshellarg', $flags)), $params->getJSignParameters());
    }

    /**
     * @param array{short: ?string, long: ?string, takesValue: bool} $option
     */
    private function render(array $option, string $key): string
    {
        try {
            $params = JSignParam::instance()->setJSignParameters([$key => '-value']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        if ($params->getPasswords() !== []) {
            return 'password ' . implode(',', array_keys($params->getPasswords()));
        }
        return $params->getJSignParameters();
    }

    /**
     * @param array{short: ?string, long: ?string, takesValue: bool} $option
     */
    private function expectedRendering(array $option): string
    {
        if ($option['short'] === self::CERTIFICATE_PASSWORD_OPTION) {
            return 'rejected';
        }
        if (in_array($option['short'], self::PASSWORD_OPTIONS, true)) {
            return 'password ' . $option['short'];
        }
        if ($option['long'] === null) {
            return escapeshellarg((string) $option['short']) . ' ' . escapeshellarg('-value');
        }
        return escapeshellarg($option['long'] . '=-value');
    }
}
