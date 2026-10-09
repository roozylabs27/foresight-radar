import React, { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import {
    Alert,
    Button,
    Carousel,
    Checkbox,
    Col,
    ConfigProvider,
    Flex,
    Form,
    Input,
    Row,
    Typography,
    theme,
} from "antd";
import {
    LockOutlined,
    RadarChartOutlined,
    UserOutlined,
} from "@ant-design/icons";

const { Title, Text, Paragraph } = Typography;

const SLIDES = [
    {
        title: "Navigasi ketidakpastian melalui radar strategis.",
        description:
            "Deteksi sinyal perubahan dan faktor pendorong industri sebelum berkembang menjadi disrupsi organisasi.",
    },
    {
        title: "Strukturkan prioritas lintas tiga horizon waktu.",
        description:
            "Petakan inisiatif ke dalam jangka pendek, menengah, dan panjang untuk mitigasi risiko serta kesiapan masa depan.",
    },
    {
        title: "Konsensus kepemimpinan berbasis data visual.",
        description:
            "Hubungkan perspektif ekonomi, teknologi, regulasi, dan pasar dalam satu radar terintegrasi untuk aksi presisi.",
    },
];

function LoginContent({ errors: serverErrors }) {
    const [loading, setLoading] = useState(false);
    const { token } = theme.useToken();

    const onFinish = (values) => {
        setLoading(true);

        router.post(route("login"), values, {
            onError: () => {
                setLoading(false);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    return (
        <>
            <Head title="Masuk ke Foresight Radar" />

            <Row className="login-desktop-fixed" style={{ minHeight: "100vh", margin: 0, overflowX: "hidden" }}>
                {/* Left Column: Brand Showcase & Carousel (50% on lg, 100% on xs) */}
                <Col
                    xs={24}
                    lg={12}
                    style={{
                        backgroundColor: "#001529",
                        color: "#ffffff",
                        padding: "56px 48px",
                        display: "flex",
                        flexDirection: "column",
                        justifyContent: "space-between",
                        minHeight: 420,
                    }}
                >
                    {/* Brand Header */}
                    <Flex align="center" gap={14}>
                        <RadarChartOutlined
                            style={{
                                fontSize: 36,
                                color: token.colorPrimary,
                            }}
                        />
                        <Flex vertical>
                            <Title
                                level={4}
                                style={{
                                    color: "#ffffff",
                                    margin: 0,
                                    lineHeight: 1.2,
                                }}
                            >
                                Foresight Radar
                            </Title>
                            <Text
                                style={{
                                    color: "rgba(255, 255, 255, 0.65)",
                                    fontSize: 12,
                                    letterSpacing: 0.8,
                                    textTransform: "uppercase",
                                }}
                            >
                                Strategic Radar Platform
                            </Text>
                        </Flex>
                    </Flex>

                    {/* Middle Carousel Showcase */}
                    <div style={{ maxWidth: 480, margin: "40px 0" }}>
                        <Carousel autoplay autoplaySpeed={5000}>
                            {SLIDES.map((slide, idx) => (
                                <div key={idx}>
                                    <div style={{ minHeight: 150, paddingBottom: 24 }}>
                                        <Title
                                            level={2}
                                            style={{
                                                color: "#ffffff",
                                                marginBottom: 16,
                                                lineHeight: 1.25,
                                            }}
                                        >
                                            {slide.title}
                                        </Title>
                                        <Paragraph
                                            style={{
                                                color: "rgba(255, 255, 255, 0.8)",
                                                fontSize: 16,
                                                lineHeight: 1.6,
                                            }}
                                        >
                                            {slide.description}
                                        </Paragraph>
                                    </div>
                                </div>
                            ))}
                        </Carousel>
                    </div>

                    {/* Footer Copyright */}
                    <Text
                        style={{
                            color: "rgba(255, 255, 255, 0.45)",
                            fontSize: 13,
                        }}
                    >
                        © 2026 Foresight Radar. Hak cipta dilindungi.
                    </Text>
                </Col>

                {/* Right Column: Ant Design Login Form (50% on lg, 100% on xs) */}
                <Col
                    xs={24}
                    lg={12}
                    style={{
                        backgroundColor: token.colorBgContainer,
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        padding: "56px 24px",
                    }}
                >
                    <div style={{ width: "100%", maxWidth: 380 }}>
                        <Title level={2} style={{ marginBottom: 8 }}>
                            Masuk ke Foresight Radar
                        </Title>
                        <Paragraph
                            type="secondary"
                            style={{ marginBottom: 32 }}
                        >
                            Silakan masukkan email dan kata sandi Anda untuk mengakses sistem.
                        </Paragraph>

                        {serverErrors?.email && (
                            <Alert
                                message={serverErrors.email}
                                type="error"
                                showIcon
                                style={{ marginBottom: 24 }}
                            />
                        )}

                        <Form
                            name="login"
                            layout="vertical"
                            disabled={loading}
                            initialValues={{ remember: false }}
                            onFinish={onFinish}
                            requiredMark={true}
                            size="large"
                        >
                            <Form.Item
                                name="email"
                                label="Email Kerja"
                                rules={[
                                    {
                                        required: true,
                                        message: "Harap masukkan email kerja Anda!",
                                    },
                                    {
                                        type: "email",
                                        message: "Format email tidak valid!",
                                    },
                                ]}
                            >
                                <Input
                                    id="email"
                                    prefix={
                                        <UserOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="analis@perusahaan.com"
                                    autoComplete="email"
                                    autoFocus
                                />
                            </Form.Item>

                            <Form.Item
                                name="password"
                                label="Kata Sandi"
                                rules={[
                                    {
                                        required: true,
                                        message: "Harap masukkan kata sandi Anda!",
                                    },
                                ]}
                            >
                                <Input.Password
                                    id="password"
                                    prefix={
                                        <LockOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="Masukkan kata sandi"
                                    autoComplete="current-password"
                                />
                            </Form.Item>

                            <Flex
                                justify="space-between"
                                align="center"
                                style={{ marginBottom: 24 }}
                            >
                                <Form.Item
                                    name="remember"
                                    valuePropName="checked"
                                    noStyle
                                >
                                    <Checkbox>Ingat saya</Checkbox>
                                </Form.Item>
                                <Link
                                    href={route("password.request")}
                                    style={{ fontSize: 14 }}
                                >
                                    Lupa kata sandi?
                                </Link>
                            </Flex>

                            <Form.Item style={{ marginBottom: 0 }}>
                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    loading={loading}
                                    block
                                >
                                    Masuk ke Platform
                                </Button>
                            </Form.Item>
                        </Form>
                    </div>
                </Col>
            </Row>
        </>
    );
}

export default function Login(props) {
    return (
        <ConfigProvider
            theme={{
                token: {
                    colorPrimary: "#1677ff",
                    borderRadius: 6,
                },
                components: {
                    Carousel: {
                        dotActiveWidth: 24,
                        dotWidth: 8,
                        dotHeight: 4,
                    },
                },
            }}
        >
            <LoginContent {...props} />
        </ConfigProvider>
    );
}
