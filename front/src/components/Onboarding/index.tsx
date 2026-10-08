import { UlamsContext } from "@ulams/sdk/react";
import {
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, A11y, Pagination } from "swiper/modules";
import { Swiper as SwiperType } from "swiper/types";
import Container from "@/components/Common/Container";
import Step from "@/components/Onboarding/Step";
import { Button, Text, Title } from "@ulams/components";
import { Col } from "react-grid-system";
import { useTranslation } from "react-i18next";
import ResponsiveImage from "@ulams/components/components/organisms/ResponsiveImage/ResponsiveImage";
import { useHistory } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";
import "swiper/css";
import "swiper/css/pagination";
import styles from "./Onboarding.module.css";

export type Translations = {
  [lang: string]: string;
};

export type OnboardingOption = {
  value: string;
  label: Translations;
};

export enum OnboardingStepType {
  radio = "radio",
  options = "options",
  slide = "slide",
}

export type OnboardingStep = {
  type: keyof typeof OnboardingStepType;
  data: string;
  order: number;
  question: Translations;
  options: OnboardingOption[];
  hint?: {
    title: Translations;
    text: Translations;
  };
  image?: string;
};

type State = {
  steps: OnboardingStep[];
  currentStep: number;
  isLastStep: boolean;
  answers?: {
    [key: string]: string;
  };
};

const Onboarding = () => {
  const { settings, fetchSettings, updateProfile, fetchProfile, user } =
    useContext(UlamsContext);
  const { t, i18n } = useTranslation();
  const history = useHistory();
  const [state, setState] = useState<State>({
    steps: [],
    currentStep: 0,
    answers: {},
    isLastStep: false,
  });
  const swiperRef = useRef<SwiperType>();

  // @ts-ignore
  if (user.value?.isOnboardingCompleted) {
    history.push(routeRoutes.home);
  }

  const getOnboardingSteps = useCallback(() => {
    const steps = Object.keys(settings.value.onboarding)
      .filter((key) => key.startsWith("step_"))
      .map((key) => settings.value.onboarding[key])
      .sort((a, b) => a.order - b.order);

    setState({
      steps: steps,
      currentStep: 0,
      isLastStep: false,
    });
  }, [settings]);

  const lastStep = useMemo(() => {
    if (settings.value.onboarding && settings.value.onboarding?.last_step) {
      return {
        ...settings.value.onboarding[`last_step`],
        image: settings.value.onboarding[`image_last_step`]
          ? settings.value.onboarding[`image_last_step`]
          : null,
      };
    } else {
      return;
    }
  }, [settings]);

  useEffect(() => {
    fetchSettings();
    getOnboardingSteps();

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fetchSettings]);

  useEffect(() => {
    if (swiperRef.current && !state.isLastStep) {
      swiperRef.current.slideTo(state.currentStep);
    }
  }, [state]);

  const handleSaveOnboarding = useCallback(async () => {
    try {
      const req = await updateProfile({
        ...state.answers,
        isOnboardingCompleted: true,
      });

      if (req.success) {
        fetchProfile();
      }
    } catch (error) {
      console.log("error", error);
    }

    history.push(routeRoutes.home);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state.answers, history]);

  const nextStepValidation = useMemo(() => {
    const currentStep = state.steps[state.currentStep];

    const answer =
      currentStep && state.answers && state.answers[currentStep.data];

    if (answer) {
      return false;
    }
    if (state.currentStep === state.steps.length) {
      setState((prev) => ({
        ...prev,
        isLastStep: true,
      }));

      return false;
    }
    return true;
  }, [state.steps, state.answers, state.currentStep]);

  const handleNextStep = useCallback(() => {
    if (!lastStep && state.currentStep === state.steps.length - 1) {
      handleSaveOnboarding();
      return;
    }
    if (state.currentStep === state.steps.length) {
      handleSaveOnboarding();
    } else {
      setState((prev) => ({
        ...prev,
        currentStep: prev.currentStep + 1,
      }));
    }
  }, [state.currentStep, state.steps.length, handleSaveOnboarding, lastStep]);

  return (
    <div
      className={`onboarding ${styles.onboarding}${
        state.isLastStep ? ` ${styles.lastStep}` : ""
      }`}
    >
      <Container>
        <Col offset={{ lg: 3 }} lg={6}>
          <div className={`onboarding__content ${styles.content}`}>
            {!state.isLastStep ? (
              <Swiper
                className="onboarding__swiper"
                modules={[Navigation, A11y, Pagination]}
                slidesPerView={1}
                allowTouchMove={false}
                initialSlide={0}
                pagination={true}
                autoHeight
                onBeforeInit={(swiper) => {
                  swiperRef.current = swiper;
                }}
              >
                {state.steps.map((step, index) => (
                  <SwiperSlide key={index}>
                    <Step
                      answers={state.answers}
                      step={step}
                      onAnswer={(value) =>
                        setState((prev) => ({
                          ...prev,
                          answers: {
                            ...prev.answers,
                            [step.data]: value,
                          },
                        }))
                      }
                    />
                  </SwiperSlide>
                ))}
              </Swiper>
            ) : null}
            {state.isLastStep && lastStep && (
              <div className={styles.lastStepContent}>
                {lastStep?.image && (
                  <ResponsiveImage
                    path={lastStep?.image}
                    srcSizes={[500, 750, 1000]}
                  />
                )}

                <Title level={2}>{lastStep?.title[i18n.language]}</Title>
                <Text>{lastStep?.text[i18n.language]}</Text>
              </div>
            )}
            <Button
              className={`next-step ${styles.nextStep}`}
              disabled={nextStepValidation}
              onClick={handleNextStep}
            >
              {t("Next")}
            </Button>
          </div>
        </Col>
      </Container>
    </div>
  );
};

export default Onboarding;
