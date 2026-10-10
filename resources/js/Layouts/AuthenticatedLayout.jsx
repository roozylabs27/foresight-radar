import React, { useState } from "react";
import {
    LogoutOutlined,
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    DownOutlined,
    DashboardOutlined,
    RadarChartOutlined,
    UsergroupAddOutlined,
    FieldTimeOutlined,
    StarOutlined,
    UserOutlined,
    FileProtectOutlined,
    FileExcelOutlined,
    DotChartOutlined,
    InsertRowAboveOutlined,
    ThunderboltOutlined,
} from "@ant-design/icons";
import {
    Layout,
    Menu,
    theme,
    Flex,
    Button,
    Dropdown,
    Space,
    FloatButton,
    Avatar,
    ConfigProvider,
} from "antd";
import { Link, usePage } from "@inertiajs/react";

const { Header, Footer, Sider } = Layout;

export default function Authenticated({ auth, user: propUser, header, children }) {
    const pageProps = usePage().props;
    const { url } = usePage();
    const effectiveAuth = auth || pageProps.auth || {};
    const user = effectiveAuth.user || propUser;
    const permissions = effectiveAuth.permissions || [];
    const role = effectiveAuth.role;

    const [items] = useState([
        {
            label: <Link href={route("dashboard.")}>Dashboard</Link>,
            icon: <DashboardOutlined />,
            key: "/dashboard",
            permission: "view-dashboard",
        },
        {
            label: <Link href={route("signals.index")}>Signal Ingestion</Link>,
            icon: <ThunderboltOutlined />,
            key: "/signals",
            permission: "view-signal",
        },
        {
            label: <Link href={route("driving-force.")}>Driving Force</Link>,
            icon: <RadarChartOutlined />,
            key: "/driving-force",
            permission: "view-driving-force",
        },
        {
            label: <Link href={route("time-horizon.")}>Time Horizon</Link>,
            icon: <FieldTimeOutlined />,
            key: "/time-horizon",
            permission: "view-time-horizon",
        },
        {
            label: <Link href={route("rating-urgency.")}>Rating of Urgency</Link>,
            icon: <StarOutlined />,
            key: "/rating-urgency",
            permission: "view-rating-urgency",
        },
        {
            label: <Link href={route("status-action.")}>Status of Action</Link>,
            icon: <FieldTimeOutlined />,
            key: "/status-action",
            permission: "view-status-action",
        },
        {
            label: <Link href={route("approval-items.")}>Approval Items</Link>,
            icon: <FileProtectOutlined />,
            key: "/approval-items",
            permission: "view-approval-items",
        },
        {
            label: <Link href={route("closed-items.")}>Closed Items</Link>,
            icon: <FileExcelOutlined />,
            key: "/closed-items",
            permission: "view-closed-items",
        },
        {
            label: "User Management",
            icon: <UsergroupAddOutlined />,
            key: "/user-management",
            permission: "view-user",
            children: [
                {
                    key: "/user-management/user",
                    label: (
                        <Link href={route("user-management.user.")}>User</Link>
                    ),
                },
            ],
        },
        {
            label: (
                <Link href={route("visualization.prioritizing.")}>
                    Prioritizing
                </Link>
            ),
            icon: <DotChartOutlined />,
            key: "/visualization/prioritizing",
            permission: "view-prioritizing",
        },
        {
            label: (
                <Link href={route("visualization.registered-list.")}>
                    Registered List
                </Link>
            ),
            icon: <InsertRowAboveOutlined />,
            key: "/visualization/registered-list",
            permission: "view-registered-list",
        },
        {
            label: (
                <Link href={route("visualization.foresight-radar.")}>
                    Foresight Radar
                </Link>
            ),
            icon: <RadarChartOutlined />,
            key: "/visualization/foresight-radar",
            permission: "view-foresight-radar",
        },
    ]);

    const actions = [
        {
            key: "profile",
            label: (
                <Link href={route("profile.edit")}>
                    <Flex gap="small" align="center">
                        <UserOutlined />
                        <span>Profil Pengguna</span>
                    </Flex>
                </Link>
            ),
        },
        {
            type: "divider",
        },
        {
            key: "logout",
            danger: true,
            label: (
                <Link
                    href={route("logout")}
                    method="post"
                    as="button"
                    type="button"
                    style={{
                        width: "100%",
                        textAlign: "left",
                        background: "none",
                        border: "none",
                        padding: 0,
                        cursor: "pointer",
                    }}
                >
                    <Flex gap="small" align="center">
                        <LogoutOutlined />
                        <span>Keluar (Logout)</span>
                    </Flex>
                </Link>
            ),
        },
    ];

    const filteredItems = items.filter(
        (item) =>
            !item.permission ||
            role === "developer" ||
            role === "super-admin" ||
            permissions.includes(item.permission)
    );

    const {
        token: { colorBgContainer },
    } = theme.useToken();

    const [collapsed, setCollapsed] = useState(false);

    return (
        <ConfigProvider
            theme={{
                token: {
                    colorPrimary: "#1677ff",
                    borderRadius: 8,
                    fontFamily:
                        "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
                },
            }}
        >
            <Layout style={{ minHeight: "100vh" }}>
                <Sider
                    breakpoint="lg"
                    collapsedWidth="64"
                    theme="light"
                    width={240}
                    collapsed={collapsed}
                    collapsible
                    trigger={null}
                    style={{
                        borderRight: "1px solid #f0f0f0",
                        position: "sticky",
                        top: 0,
                        height: "100vh",
                        overflowY: "auto",
                        zIndex: 20,
                    }}
                >
                    <div
                        style={{
                            height: 64,
                            display: "flex",
                            alignItems: "center",
                            justifyContent: collapsed ? "center" : "flex-start",
                            padding: collapsed ? "0" : "0 18px",
                            gap: 12,
                            borderBottom: "1px solid #f0f0f0",
                            transition: "all 0.2s",
                        }}
                    >
                        <div
                            style={{
                                width: 36,
                                height: 36,
                                borderRadius: 8,
                                background:
                                    "linear-gradient(135deg, #1677ff 0%, #0958d9 100%)",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                boxShadow: "0 2px 8px rgba(22, 119, 255, 0.35)",
                                flexShrink: 0,
                            }}
                        >
                            <RadarChartOutlined
                                style={{ color: "#fff", fontSize: 20 }}
                            />
                        </div>
                        {!collapsed && (
                            <div style={{ overflow: "hidden", whiteSpace: "nowrap" }}>
                                <span
                                    style={{
                                        fontSize: 15,
                                        fontWeight: 700,
                                        color: "#001529",
                                        letterSpacing: "-0.2px",
                                        display: "block",
                                        lineHeight: 1.2,
                                    }}
                                >
                                    Foresight Radar
                                </span>
                                <span
                                    style={{
                                        fontSize: 11,
                                        color: "#8c8c8c",
                                        fontWeight: 500,
                                        letterSpacing: "0.5px",
                                        textTransform: "uppercase",
                                    }}
                                >
                                    Strategic Intel
                                </span>
                            </div>
                        )}
                    </div>
                    <Menu
                        theme="light"
                        mode="inline"
                        selectedKeys={[url]}
                        items={filteredItems}
                        style={{ borderRight: "none", marginTop: 8 }}
                    />
                </Sider>
                <Layout>
                    <Header
                        style={{
                            padding: "0 20px",
                            background: colorBgContainer,
                            borderBottom: "1px solid #f0f0f0",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "space-between",
                            height: 64,
                            position: "sticky",
                            top: 0,
                            zIndex: 10,
                        }}
                    >
                        <Flex align="center" gap="middle">
                            <Button
                                type="text"
                                icon={
                                    collapsed ? (
                                        <MenuUnfoldOutlined />
                                    ) : (
                                        <MenuFoldOutlined />
                                    )
                                }
                                onClick={() => setCollapsed(!collapsed)}
                                style={{
                                    fontSize: 16,
                                    width: 40,
                                    height: 40,
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                }}
                                aria-label={
                                    collapsed
                                        ? "Buka menu navigasi"
                                        : "Tutup menu navigasi"
                                }
                            />
                            {header && (
                                <div style={{ display: "flex", alignItems: "center" }}>
                                    {header}
                                </div>
                            )}
                        </Flex>
                        <Dropdown
                            placement="bottomRight"
                            menu={{
                                items: actions,
                            }}
                            trigger={["click"]}
                        >
                            <Button
                                type="text"
                                style={{
                                    height: 44,
                                    padding: "4px 10px",
                                    display: "flex",
                                    alignItems: "center",
                                    gap: 10,
                                }}
                            >
                                <Avatar
                                    size="small"
                                    style={{
                                        backgroundColor: "#1677ff",
                                        fontWeight: 600,
                                    }}
                                >
                                    {user?.name
                                        ? user.name.charAt(0).toUpperCase()
                                        : "U"}
                                </Avatar>
                                <span
                                    style={{
                                        fontWeight: 500,
                                        color: "#262626",
                                        maxWidth: 160,
                                        overflow: "hidden",
                                        textOverflow: "ellipsis",
                                        whiteSpace: "nowrap",
                                    }}
                                >
                                    {user?.name}
                                </span>
                                <DownOutlined
                                    style={{ fontSize: 10, color: "#8c8c8c" }}
                                />
                            </Button>
                        </Dropdown>
                    </Header>
                    {children}
                    <Footer
                        style={{
                            textAlign: "center",
                            color: "#8c8c8c",
                            fontSize: 12,
                            padding: "10px 24px",
                        }}
                    >
                        Foresight Radar © {new Date().getFullYear()} Strategic Horizon Scanning. All Rights Reserved.
                    </Footer>
                </Layout>
                <FloatButton.BackTop />
            </Layout>
        </ConfigProvider>
    );
}
