import React from "react";
import { Link } from "@inertiajs/react";
import { Card, ConfigProvider, Flex, Layout, Typography, theme } from "antd";
import { RadarChartOutlined } from "@ant-design/icons";

const { Title, Text } = Typography;
const { Content } = Layout;

function GuestLayoutContent({ children }) {
    const { token } = theme.useToken();

    return (
        <Layout
            style={{
                minHeight: "100vh",
                backgroundColor: "#f8fafc",
                display: "flex",
                flexDirection: "column",
                justifyContent: "center",
                alignItems: "center",
                padding: "32px 16px",
            }}
        >
            <Content
                style={{
                    width: "100%",
                    maxWidth: 440,
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "center",
                }}
            >
                {/* Brand Header */}
                <Flex
                    vertical
                    align="center"
                    style={{ marginBottom: 28, textAlign: "center" }}
                >
                    <Link href="/">
                        <RadarChartOutlined
                            style={{
                                fontSize: 44,
                                color: token.colorPrimary,
                                marginBottom: 8,
                            }}
                        />
                    </Link>
                    <Title
                        level={3}
                        style={{
                            margin: 0,
                            color: "#0f172a",
                            fontWeight: 700,
                            letterSpacing: -0.4,
                        }}
                    >
                        Foresight Radar
                    </Title>
                    <Text
                        style={{
                            color: "#64748b",
                            fontSize: 12,
                            letterSpacing: 0.8,
                            textTransform: "uppercase",
                            marginTop: 4,
                        }}
                    >
                        Strategic Radar Platform
                    </Text>
                </Flex>

                {/* Card Container */}
                <Card
                    bordered={true}
                    style={{
                        width: "100%",
                        borderRadius: 12,
                        boxShadow: "0 4px 20px -2px rgba(15, 23, 42, 0.06)",
                        borderColor: "#e2e8f0",
                    }}
                    styles={{
                        body: {
                            padding: "32px 28px",
                        },
                    }}
                >
                    {children}
                </Card>

                {/* Footer Copyright */}
                <Text
                    style={{
                        marginTop: 24,
                        color: "#94a3b8",
                        fontSize: 13,
                        textAlign: "center",
                    }}
                >
                    © 2026 Foresight Radar. Hak cipta dilindungi.
                </Text>
            </Content>
        </Layout>
    );
}

export default function GuestLayout(props) {
    return (
        <ConfigProvider
            theme={{
                token: {
                    colorPrimary: "#1677ff",
                    borderRadius: 6,
                },
            }}
        >
            <GuestLayoutContent {...props} />
        </ConfigProvider>
    );
}
