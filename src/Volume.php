<?php
/**
 * @link https://www.vaersaagod.no/
 * @copyright Copyright (c) Værsågod
 * @license MIT
 */

namespace vaersaagod\dospaces;

use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

use Craft;
use craft\base\FlysystemVolume;
use craft\helpers\Assets;
use craft\helpers\DateTimeHelper;
use DateTime;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

/**
 * Class Volume
 *
 * @property mixed $settingsHtml
 * @property string $rootUrl
 * @author Værsågod, www.vaersaagod.no
 * @since 3.0
 */
class Volume extends FlysystemVolume
{

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'DigitalOcean Spaces';
    }

    // Properties
    // =========================================================================

    /**
     * @var bool Whether this is a local source or not. Defaults to false.
     */
    protected $isVolumeLocal = false;

    /**
     * @var string Subfolder to use
     */
    public $subfolder = '';

    /**
     * @var string DO key ID
     */
    public $keyId = '';

    /**
     * @var string DO key secret
     */
    public $secret = '';

    /**
     * @var string DO Endpoint
     */
    public $endpoint = '';

    /**
     * @var string Bucket to use
     */
    public $bucket = '';

    /**
     * @var string Region to use
     */
    public $region = '';

    /**
     * @var string Cache expiration period.
     */
    public $expires = '';

    /**
     * @var string Content Disposition value.
     */
    public $contentDisposition = '';

    /**
     * @var string Asset Permissions value.
     */
    public $assetPermissions = '';


    // Getters
    // =========================================================================

	public function getSubfolder ()
	{
		return \Craft::parseEnv($this->subfolder);
	}

	public function getKeyId ()
	{
		return \Craft::parseEnv($this->keyId);
	}

	public function getSecret ()
	{
		return \Craft::parseEnv($this->secret);
	}

	public function getEndpoint ()
	{
		return \Craft::parseEnv($this->endpoint);
	}

	public function getBucket ()
	{
		return \Craft::parseEnv($this->bucket);
	}

	public function getRegion ()
	{
		return \Craft::parseEnv($this->region);
    }
    
    public function getPermission ()
    {
        return \Craft::parseEnv($this->permission);
    }
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['keyId', 'secret', 'region', 'bucket', 'endpoint', 'permission'], 'required'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('dospaces/volumeSettings', [
            'volume' => $this,
            'periods' => array_merge(['' => ''], Assets::periodList()),
            'contentDispositionOptions' => [
                '' => '--- none ---',
                'inline' => 'inline',
                'attachment' => 'attachment'
            ],
            'contentDisposition' => $this->contentDisposition,
            'assetPermissionsOptions' => [
                'private' => 'private',
                'public' => 'public-read',
            ],
            'assetPermissions' => $this->assetPermissions
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getRootUrl()
    {
        if (($rootUrl = parent::getRootUrl()) !== false && $this->getSubfolder()) {
            $rootUrl .= rtrim($this->getSubfolder(), '/') . '/';
        }
        return $rootUrl;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @return AwsS3Adapter
     */
    protected function createAdapter()
    {
        $config = $this->_getConfigArray();

        $client = static::client($config);

        return new AwsS3Adapter($client, $this->getBucket(), $this->getSubfolder());
    }

    /**
     * Get the Amazon S3 client.
     *
     * @param $config
     * @return S3Client
     */
    protected static function client(array $config = []): S3Client
    {
        return new S3Client($config);
    }

    /**
     * @inheritdoc
     */
    protected function addFileMetadataToConfig(array $config): array
    {
        if (!empty($this->expires) && DateTimeHelper::isValidIntervalString($this->expires)) {
            $expires = new DateTime();
            $now = new DateTime();
            $expires->modify('+' . $this->expires);
            $diff = $expires->format('U') - $now->format('U');
            $config['CacheControl'] = 'max-age=' . $diff . ', must-revalidate';
            $config['ContentDisposition'] = $this->contentDisposition;
        }

        return parent::addFileMetadataToConfig($config);
    }

    // Private Methods
    // =========================================================================

    /**
     * Get the config array for AWS Client.
     *
     * @return array
     */
    private function _getConfigArray()
    {
        $keyId = $this->getKeyId();
        $secret = $this->getSecret();
        $region = $this->getRegion();
        $endpoint = $this->getEndpoint();
        $permission = $this->getPermission();

        return self::_buildConfigArray($keyId, $secret, $region, $endpoint, $permission);
    }

    /**
     * Build the config array
     *
     * @param $keyId
     * @param $secret
     * @param $region
     * @param $endpoint
     * @return array
     */
    private static function _buildConfigArray($keyId = null, $secret = null, $region = null, $endpoint = null, $permission = null): array
    {
        $config = [
            'region' => $region,
            'endpoint' => $endpoint,
            'version' => 'latest',
            'ACL' => $permission,
            'credentials' => [
                'key' => $keyId,
                'secret' => $secret,
            ],
        ];

        $client = Craft::createGuzzleClient();
        $config['http_handler'] = new GuzzleHandler($client);

        return $config;
    }
}
