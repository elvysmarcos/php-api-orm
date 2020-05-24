<?php

namespace APIORM\Enums;

abstract class TypeResponseEnum
{
    const Success = 200;
    const Created = 201;
    const Accepted = 202;
    const NoContent = 204;
    const BadRequest = 400;
    const Unauthorized = 401;
    const Forbidden = 403;
    const LogoutAll = 406;
    const Logout = 410;
    const Entity = 409;
    const SQL = 412;
    const Exception = 500;
    const NotImplemented = 501;
}