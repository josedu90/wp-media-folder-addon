<?php

namespace WP_Media_Folder\Aws\Signature;

use WP_Media_Folder\Aws\Credentials\CredentialsInterface;
use WP_Media_Folder\Psr\Http\Message\RequestInterface;
/**
 * Interface used to provide interchangeable strategies for signing requests
 * using the various AWS signature protocols.
 */
interface SignatureInterface
{
    /**
     * Signs the specified request with an AWS signing protocol by using the
     * provided AWS account credentials and adding the required headers to the
     * request.
     *
     * @param RequestInterface     $request     Request to sign
     * @param CredentialsInterface $credentials Signing credentials
     *
     * @return RequestInterface Returns the modified request.
     */
    public function signRequest(\WP_Media_Folder\Psr\Http\Message\RequestInterface $request, \WP_Media_Folder\Aws\Credentials\CredentialsInterface $credentials);
    /**
     * Create a pre-signed request.
     *
     * @param RequestInterface     $request     Request to sign
     * @param CredentialsInterface $credentials Credentials used to sign
     * @param int|string|\DateTime $expires The time at which the URL should
     *     expire. This can be a Unix timestamp, a PHP DateTime object, or a
     *     string that can be evaluated by strtotime.
     *
     * @return RequestInterface
     */
    public function presign(\WP_Media_Folder\Psr\Http\Message\RequestInterface $request, \WP_Media_Folder\Aws\Credentials\CredentialsInterface $credentials, $expires);
}
