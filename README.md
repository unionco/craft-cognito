![Logo](resources/img/plugin-logo.png)

# Craft Cognito Auth plugin

Enable authentication to Craft using [AWS Cognito](https://aws.amazon.com/cognito/).

## Overview

This plugin enables requests to Craft to be securely authenticated in the presence of a **Cognito JWT** that can be successfully verified as matching a JWKS signature.

> JSON Web Tokens are an open, industry standard RFC 7519 method for representing claims securely between two parties.
>
> -- <cite>[jwt.io](https://jwt.io/)</cite>

## Features
- Create and verify users with Cognito.
- Authenticate requests to Craft from mobile apps and websites via JWT.
- SAML Authentication.

## Requirements

This plugin requires Craft CMS 4.0 or later.

## Installation

1. Follow the [Craft CMS documentation](https://craftcms.com/docs/nitro/2.x/plugin-development.html) to set up your local Craft instance.

2. Load the plugin with Composer:

    `nitro composer require unionco/craft-cognito-auth`

3. On your browser, open your local Craft Control Panel, navigate to Settings → Plugins, and click the "Install" button for Craft Cognito Auth.

## Configuration

Navigate to the settings page of the plugin and enter required settings to activate the plugin:

| Setting                    | Description                                                                                 |
| -------------------------- | ------------------------------------------------------------------------------------------- |
| **General configuration**  |                                                                                             |
| `Auto create user`         | Optional. Enable to auto-create a public user when provided a verifiable JWT.               |
| **Cognito configuration**  |                                                                                             |
| `Enable JWT token handling`| Optional. Enable to automatically parse incoming JWT tokens and try to login the user
| `AWS Cognito region`       | Mandatory. AWS cognito region.                                                              |
| `AWS Cognito app client id`| Mandatory. AWS Cognito app client id (under App integration -> app client settings).        |
| `AWS Cognito app client secret` | Added by Union 9/5/22. Configured client secret for backend requests. |
| `AWS Cognito user pool id` | Mandatory. AWS Cognito user pool id (under General settings).                               |
| `JSON Web Key Set URL`     | Mandatory. JSON Web Key Set URL (JWKS), used for verifying incoming Cognito JWTs.           |
| **SAML configuration**     |                                                                                             |
| `SAML token handling`      | Optional. Enable to automatically parse incoming SAML tokens and try to login the user      |
| `SAML Certificate`         | Mandatory. Your SAML Certificate, used for verifying incoming SAML messages                 |
| `SAML Login URL`           | Mandatory. The SAML IdP login URL                                                           |

This plugin asumes AWS Cognito is configured so that [users sign up and sign in with email instead of username](https://docs.aws.amazon.com/cognito/latest/developerguide/user-pool-settings-attributes.html#user-pool-settings-aliases-settings-option-2) and that the App client being used has the sign-in API for server-based authentication (ADMIN_NO_SRP_AUTH) enabled as stated in the [AWS docs](https://docs.aws.amazon.com/cognito/latest/developerguide/amazon-cognito-user-pools-authentication-flow.html?icmpid=docs_cognito_console#amazon-cognito-user-pools-server-side-authentication-flow)

## Usage

The plugin will attempt to verify any incoming requests with a JWT present in the `Authentication` header with a `Bearer` prefix, or with the simpler `X-Access-Token` header value. An example:

```shell
# With Authorization: Bearer
curl --header "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.XbPfbIHMI6arZ3Y922BhjWgQzWXcXNrz0ogtVhfEd2o" MYCRAFTSITE.com

# With X-Access-Token
curl --header "X-Access-Token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.XbPfbIHMI6arZ3Y922BhjWgQzWXcXNrz0ogtVhfEd2o" MYCRAFTSITE.com
```

The plugin will attempt to verify the token using the [lcobucci/jwt](https://github.com/lcobucci/jwt) package for PHP. The package adheres to the [IANA specifications](https://www.iana.org/assignments/jwt/jwt.xhtml) for JWTs.

If a provided token can be verified AND can be matched to a user account with a username matching the provided `sub` key, the user will be authenticated and the request allowed to continue.

If the token is verifiable but a matching user account does NOT exist, but the `Auto create user` setting is enabled AND public registration is enabled in the Craft settings, a new user account will be created on-the-fly and the new user then logged in.

This plugin provides example templates for you to use as a reference when building out your authentication solution. The example templates can by found in the [templates](templates/) folder.

## Deployment

1. Update version number in `composer.json`.
2. Add a new entry in `CHANGELOG.md` documenting the changes made.
3. Push a new tag matching the new version number with the following format: `vX.Y.Z`.

## Contributing

Contributions are most welcome! Feel free to open a new issue or pull request to make this project better.

## Credits

A big thank you to:
- [craft-jwt-auth](https://github.com/edenspiekermann/craft-jwt-auth) - Copyright (c) 2019 Mike Pierce [MIT License](https://github.com/edenspiekermann/craft-jwt-auth/blob/develop/LICENSE.md) for the initial codebase.
- [@goraxan](https://github.com/goraxan) for the ongoing development.

## License

This repo is covered under the [MIT License](LICENSE).

## Union Resources
Adding client secret setting:
https://docs.aws.amazon.com/cognito-user-identity-pools/latest/APIReference/API_AdminInitiateAuth.html#API_AdminInitiateAuth_RequestParameters
Also not previously documented - in order to authenticate against any AWS service, you must configure the `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` with an appropriate programmatic-access IAM user (in this case, the user must have Cognito access).