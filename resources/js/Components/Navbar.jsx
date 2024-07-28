import React, { useState } from "react";
import { Layout, Flex, Button, Dropdown, Space } from "antd";
import {
    UserOutlined,
    LogoutOutlined,
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    DownOutlined,
} from "@ant-design/icons";
import { Link } from "@inertiajs/react";

export default function Navbar({
    collapsed,
    setCollapsed,
    colorBgContainer,
    user,
}) {
    const { Header } = Layout;
    const [items, setItems] = useState([
        {
            key: "0",
            label: (
                <Link
                    href={route("profile.edit")}
                >
                    <Flex gap="middle" vertical={false}>
                        <UserOutlined />
                        Profile
                    </Flex>
                </Link>
            ),
        },
        {
            key: "1",
            label: (
                <Link
                    href={route("logout")}
                    method="post"
                    as="button"
                    type="button"
                >
                    <Flex gap="middle" vertical={false}>
                        <LogoutOutlined />
                        Logout
                    </Flex>
                </Link>
            ),
        },
    ]);

    return (
        <>
            <Header
                className="p-0"
                style={{
                    background: colorBgContainer,
                }}
            >
                <Flex
                    className="w-100"
                    justify="space-between"
                    align="flex-start"
                >
                    <Button
                        type="text"
                        icon={
                            collapsed ? (
                                <MenuUnfoldOutlined />
                            ) : (
                                <MenuFoldOutlined />
                            )
                        }
                        className="w-[64px] h-[64px] text-[16px]"
                        onClick={() => setCollapsed(!collapsed)}
                    />
                    <Dropdown
                        placement="topLeft"
                        menu={{
                            items,
                        }}
                        trigger={["hover"]}
                    >
                        <a
                            className="mr-[20px]"
                            onClick={(e) => e.preventDefault()}
                        >
                            <Space size="small">
                                Hi, {user ? user.name : "Member"}
                                <DownOutlined />
                            </Space>
                        </a>
                    </Dropdown>
                </Flex>
            </Header>
        </>
    );
}
