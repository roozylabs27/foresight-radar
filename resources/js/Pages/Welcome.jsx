import React from "react";
import { Link, Head } from "@inertiajs/react";
import {
    Layout,
    Row,
    Col,
    Button,
    Card,
    Typography,
    Space,
    Tag,
    ConfigProvider,
} from "antd";
import {
    RadarChartOutlined,
    FieldTimeOutlined,
    DotChartOutlined,
    SafetyCertificateOutlined,
    ArrowRightOutlined,
    LoginOutlined,
    UserAddOutlined,
    DashboardOutlined,
    CompassOutlined,
} from "@ant-design/icons";

const { Header, Content, Footer } = Layout;
const { Title, Text, Paragraph } = Typography;

export default function Welcome({ auth, canRegister = true }) {
    const user = auth?.user;

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
            <Head title="Foresight Radar - Strategic Horizon Scanning" />

            <Layout
                style={{
                    minHeight: "100vh",
                    background:
                        "radial-gradient(ellipse at 50% 0%, #f0f7ff 0%, #ffffff 70%)",
                }}
            >
                <Header
                    style={{
                        background: "rgba(255, 255, 255, 0.9)",
                        backdropFilter: "blur(8px)",
                        borderBottom: "1px solid #f0f0f0",
                        position: "sticky",
                        top: 0,
                        zIndex: 100,
                        padding: "0 32px",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        height: 68,
                    }}
                >
                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: 12,
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
                            }}
                        >
                            <RadarChartOutlined
                                style={{ color: "#fff", fontSize: 20 }}
                            />
                        </div>
                        <div>
                            <span
                                style={{
                                    fontSize: 16,
                                    fontWeight: 700,
                                    color: "#001529",
                                    letterSpacing: "-0.3px",
                                    display: "block",
                                    lineHeight: 1.2,
                                }}
                            >
                                Foresight Radar
                            </span>
                            <span
                                style={{
                                    fontSize: 10,
                                    color: "#8c8c8c",
                                    fontWeight: 600,
                                    letterSpacing: "0.8px",
                                    textTransform: "uppercase",
                                }}
                            >
                                Strategic Horizon Scanning
                            </span>
                        </div>
                    </div>

                    <Space size="middle">
                        {user ? (
                            <Link href={route("dashboard.")}>
                                <Button
                                    type="primary"
                                    icon={<DashboardOutlined />}
                                    size="middle"
                                >
                                    Buka Dashboard
                                </Button>
                            </Link>
                        ) : (
                            <>
                                <Link href={route("login")}>
                                    <Button
                                        type="default"
                                        icon={<LoginOutlined />}
                                    >
                                        Masuk
                                    </Button>
                                </Link>
                                {canRegister && (
                                    <Link href={route("register")}>
                                        <Button
                                            type="primary"
                                            icon={<UserAddOutlined />}
                                        >
                                            Daftar Akun
                                        </Button>
                                    </Link>
                                )}
                            </>
                        )}
                    </Space>
                </Header>

                <Content style={{ padding: "48px 24px", maxWidth: 1200, margin: "0 auto", width: "100%" }}>
                    <div style={{ textAlign: "center", maxWidth: 820, margin: "0 auto 60px" }}>
                        <Tag
                            color="blue"
                            icon={<CompassOutlined />}
                            style={{
                                padding: "4px 14px",
                                borderRadius: 16,
                                fontSize: 12,
                                fontWeight: 600,
                                textTransform: "uppercase",
                                letterSpacing: "0.5px",
                                marginBottom: 20,
                            }}
                        >
                            Sistem Intelijen Strategis Organisasi
                        </Tag>

                        <Title
                            level={1}
                            style={{
                                fontSize: "clamp(28px, 4vw, 44px)",
                                fontWeight: 800,
                                color: "#001529",
                                letterSpacing: "-0.8px",
                                lineHeight: 1.2,
                                marginBottom: 20,
                            }}
                        >
                            Navigasi Ketidakpastian Masa Depan dengan Foresight Radar
                        </Title>

                        <Paragraph
                            style={{
                                fontSize: 16,
                                color: "#595959",
                                lineHeight: 1.6,
                                maxWidth: 680,
                                margin: "0 auto 32px",
                            }}
                        >
                            Platform horizon scanning cerdas untuk mendeteksi sinyal lemah
                            (weak signals), menganalisis megatrend, dan memetakan driving forces
                            ke dalam matriks prioritas aksi berbasis bukti.
                        </Paragraph>

                        <Space size="middle" wrap style={{ justifyContent: "center" }}>
                            {user ? (
                                <Link href={route("dashboard.")}>
                                    <Button
                                        type="primary"
                                        size="large"
                                        icon={<DashboardOutlined />}
                                        style={{ height: 48, padding: "0 32px", fontSize: 15 }}
                                    >
                                        Akses Dashboard Utama
                                    </Button>
                                </Link>
                            ) : (
                                <>
                                    <Link href={route("login")}>
                                        <Button
                                            type="primary"
                                            size="large"
                                            icon={<ArrowRightOutlined />}
                                            style={{ height: 48, padding: "0 32px", fontSize: 15 }}
                                        >
                                            Mulai Sekarang
                                        </Button>
                                    </Link>
                                    {canRegister && (
                                        <Link href={route("register")}>
                                            <Button
                                                size="large"
                                                style={{ height: 48, padding: "0 28px", fontSize: 15 }}
                                            >
                                                Registrasi Akun Baru
                                            </Button>
                                        </Link>
                                    )}
                                </>
                            )}
                        </Space>
                    </div>

                    <Row gutter={[24, 24]}>
                        <Col xs={24} sm={12} lg={6}>
                            <Card
                                bordered={false}
                                hoverable
                                style={{
                                    height: "100%",
                                    borderRadius: 12,
                                    boxShadow: "0 2px 10px rgba(0,0,0,0.04)",
                                    borderTop: "3px solid #1677ff",
                                }}
                            >
                                <div
                                    style={{
                                        width: 44,
                                        height: 44,
                                        borderRadius: 10,
                                        background: "#e6f4ff",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        marginBottom: 16,
                                    }}
                                >
                                    <FieldTimeOutlined
                                        style={{ fontSize: 22, color: "#1677ff" }}
                                    />
                                </div>
                                <Title level={4} style={{ fontSize: 16, marginBottom: 8 }}>
                                    3 Time Horizons
                                </Title>
                                <Text type="secondary" style={{ fontSize: 13, lineHeight: 1.5 }}>
                                    Klasifikasi sinyal masa depan dalam Horizon 1 (Jangka Pendek),
                                    Horizon 2 (Menengah), dan Horizon 3 (Transformasi Jangka Panjang).
                                </Text>
                            </Card>
                        </Col>

                        <Col xs={24} sm={12} lg={6}>
                            <Card
                                bordered={false}
                                hoverable
                                style={{
                                    height: "100%",
                                    borderRadius: 12,
                                    boxShadow: "0 2px 10px rgba(0,0,0,0.04)",
                                    borderTop: "3px solid #52c41a",
                                }}
                            >
                                <div
                                    style={{
                                        width: 44,
                                        height: 44,
                                        borderRadius: 10,
                                        background: "#f6ffed",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        marginBottom: 16,
                                    }}
                                >
                                    <RadarChartOutlined
                                        style={{ fontSize: 22, color: "#52c41a" }}
                                    />
                                </div>
                                <Title level={4} style={{ fontSize: 16, marginBottom: 8 }}>
                                    Radar Multi-Dimensi
                                </Title>
                                <Text type="secondary" style={{ fontSize: 13, lineHeight: 1.5 }}>
                                    Visualisasi polar 360° yang mencakup dimensi Sosial, Teknologi,
                                    Ekonomi, Regulasi, dan Lingkungan secara terintegrasi.
                                </Text>
                            </Card>
                        </Col>

                        <Col xs={24} sm={12} lg={6}>
                            <Card
                                bordered={false}
                                hoverable
                                style={{
                                    height: "100%",
                                    borderRadius: 12,
                                    boxShadow: "0 2px 10px rgba(0,0,0,0.04)",
                                    borderTop: "3px solid #fa8c16",
                                }}
                            >
                                <div
                                    style={{
                                        width: 44,
                                        height: 44,
                                        borderRadius: 10,
                                        background: "#fff7e6",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        marginBottom: 16,
                                    }}
                                >
                                    <DotChartOutlined
                                        style={{ fontSize: 22, color: "#fa8c16" }}
                                    />
                                </div>
                                <Title level={4} style={{ fontSize: 16, marginBottom: 8 }}>
                                    Prioritizing Matrix
                                </Title>
                                <Text type="secondary" style={{ fontSize: 13, lineHeight: 1.5 }}>
                                    Korelasi terukur antara rating urgensi dan status tindakan untuk
                                    memastikan alokasi sumber daya tepat sasaran.
                                </Text>
                            </Card>
                        </Col>

                        <Col xs={24} sm={12} lg={6}>
                            <Card
                                bordered={false}
                                hoverable
                                style={{
                                    height: "100%",
                                    borderRadius: 12,
                                    boxShadow: "0 2px 10px rgba(0,0,0,0.04)",
                                    borderTop: "3px solid #722ed1",
                                }}
                            >
                                <div
                                    style={{
                                        width: 44,
                                        height: 44,
                                        borderRadius: 10,
                                        background: "#f9f0ff",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        marginBottom: 16,
                                    }}
                                >
                                    <SafetyCertificateOutlined
                                        style={{ fontSize: 22, color: "#722ed1" }}
                                    />
                                </div>
                                <Title level={4} style={{ fontSize: 16, marginBottom: 8 }}>
                                    Governance & Approval
                                </Title>
                                <Text type="secondary" style={{ fontSize: 13, lineHeight: 1.5 }}>
                                    Alur verifikasi dan persetujuan terstruktur dari kontributor
                                    hingga strategic committee untuk kredibilitas data.
                                </Text>
                            </Card>
                        </Col>
                    </Row>
                </Content>

                <Footer
                    style={{
                        textAlign: "center",
                        background: "#ffffff",
                        borderTop: "1px solid #f0f0f0",
                        color: "#8c8c8c",
                        fontSize: 13,
                        padding: "24px 32px",
                    }}
                >
                    Foresight Radar © {new Date().getFullYear()} Strategic Horizon Scanning System. All Rights Reserved.
                </Footer>
            </Layout>
        </ConfigProvider>
    );
}
